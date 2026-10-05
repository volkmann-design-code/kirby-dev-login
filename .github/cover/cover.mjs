/**
 * Renders `.github/cover.png`: 1280 × 640 at 2× (2:1, the format of Kirby's
 * plugin directory and of GitHub's social preview), the same on every run:
 *
 * - a fresh Kirby site (`site/`) with the same made-up accounts, Kirby
 *   being the repository's dev dependency (`composer install`)
 * - pinned Chromium (Playwright) and pinned fonts (Fontsource): Inter
 *   replaces the Panel's system font, JetBrains Mono its monospace one
 *
 *   npm ci && npx playwright install chromium && npm run cover
 */
import { execFileSync, spawn } from "node:child_process";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync } from "node:fs";
import { createServer } from "node:net";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "playwright";

const here = dirname(fileURLToPath(import.meta.url));
const repo = join(here, "..", "..");
const site = join(here, "site");
const out = join(here, "..", "cover.png");

// the dialog appears 1.1 times its size in the cover (cover.html)
const scale = 1.1;
// the hand's fingertip in its 30 px wide SVG
const fingertip = { x: (11 / 32) * 30, y: (3.5 / 32) * 30 };

const font = (path, family, weight) => {
	const file = readFileSync(join(here, "node_modules", path)).toString("base64");
	return `@font-face { font-family: "${family}"; font-weight: ${weight}; src: url(data:font/woff2;base64,${file}) format("woff2"); }`;
};
const fonts = [
	font("@fontsource-variable/inter/files/inter-latin-wght-normal.woff2", "Inter Variable", "100 900"),
	font("@fontsource/jetbrains-mono/files/jetbrains-mono-latin-400-normal.woff2", "JetBrains Mono", 400),
].join("\n");

const freePort = () =>
	new Promise((resolve) => {
		const server = createServer().listen(0, "127.0.0.1", () => {
			const { port } = server.address();
			server.close(() => resolve(port));
		});
	});

// content, accounts and sessions in a temp folder; the plugin is this repository
const data = mkdtempSync(join(tmpdir(), "kirby-dev-login-cover-"));
mkdirSync(join(data, "plugins"));
symlinkSync(repo, join(data, "plugins", "dev-login"));
const env = { ...process.env, COVER_DATA: data };
execFileSync("php", [join(site, "index.php"), "seed"], { env, stdio: "inherit" });

const port = await freePort();
const base = `http://127.0.0.1:${port}`;
const server = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", site, join(repo, "kirby", "router.php")], {
	env,
	stdio: "ignore",
});
const browser = await chromium.launch({ args: ["--font-render-hinting=none"] });

try {
	for (let i = 0; ; i++) {
		try {
			await fetch(`${base}/panel/login`);
			break;
		} catch (error) {
			if (i === 50) throw error;
			await new Promise((resolve) => setTimeout(resolve, 100));
		}
	}

	// 1. the real login dialog, with Inter as the Panel's font
	const page = await browser.newPage({ deviceScaleFactor: 2, viewport: { width: 900, height: 900 } });
	await page.goto(`${base}/panel/login`);
	await page.addStyleTag({
		content: `${fonts}
			:root { --font-sans: "Inter Variable", sans-serif; --font-mono: "JetBrains Mono", monospace; }`,
	});
	const dialog = page.locator(".k-login-dialog");
	const third = page.locator(".dev-login button").nth(2);
	await third.waitFor();
	await page.evaluate(async () => {
		await document.fonts.ready;
		// no focus ring on the email field
		document.activeElement?.blur();
	});
	const box = await dialog.boundingBox();
	const button = await third.boundingBox();
	const shot = await dialog.screenshot({ animations: "disabled", omitBackground: true });

	// 2. the cover: the fingertip in the third button's right edge
	const tip = {
		x: (button.x + button.width - box.x - 4) * scale,
		y: (button.y + button.height / 2 - box.y + 2) * scale,
	};
	const html = readFileSync(join(here, "cover.html"), "utf8")
		.replace("/*{{fonts}}*/", fonts)
		.replace("{{dialog}}", `data:image/png;base64,${shot.toString("base64")}`)
		.replace("{{pointer}}", `left: ${(tip.x - fingertip.x).toFixed(1)}px; top: ${(tip.y - fingertip.y).toFixed(1)}px`);
	const cover = await browser.newPage({ deviceScaleFactor: 2, viewport: { width: 1280, height: 640 } });
	await cover.setContent(html);
	await cover.evaluate(() => document.fonts.ready);
	await cover.screenshot({ path: out, animations: "disabled" });
	console.log(`${out}, Kirby ${execFileSync("php", ["-r", `echo json_decode(file_get_contents('${join(repo, "kirby", "composer.json")}'))->version;`])}`);
} finally {
	await browser.close();
	server.kill();
	rmSync(data, { recursive: true, force: true });
}
