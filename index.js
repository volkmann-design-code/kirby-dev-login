/**
 * Kirby's login form plugin (`window.panel.plugins.login`): the Panel
 * renders this in place of its form. It renders Kirby's form unchanged and
 * a button per account below it (`index.php`). Render functions instead
 * of a template, so it works without the Panel's Vue template compiler
 * (`panel.vue.compiler: false`); a broken login form would lock you out.
 */
panel.plugin("volkmann-design-code/dev-login", {
	login: {
		// what the login view passes to Kirby's form
		props: {
			methods: { type: Array, default: () => [] },
			value: { type: Object, default: () => ({}) },
		},
		data: () => ({ users: [] }),
		async created() {
			try {
				this.users = (await this.$api.get("dev-login")).users;
			} catch {
				// off (404): only Kirby's form
			}
		},
		methods: {
			async login(email) {
				try {
					await this.$api.post("dev-login", { email });
					// what Kirby's form does after a login
					await this.$reload({ globals: ["$system", "$translation"] });
				} catch (error) {
					this.$emit("error", error);
				}
			},
		},
		render(h) {
			const form = h("k-login-form", {
				props: { methods: this.methods, value: this.value },
				on: this.$listeners,
			});

			if (this.users.length === 0) {
				return form;
			}

			const buttons = this.users.map((user) =>
				h("k-button", {
					props: {
						icon: "user",
						size: "sm",
						text: `${user.name} (${user.role})`,
						title: user.email,
						variant: "filled",
					},
					on: { click: () => this.login(user.email) },
				}),
			);

			return h("div", [
				form,
				h("nav", { class: "dev-login", attrs: { "aria-labelledby": "dev-login-label" } }, [
					h(
						"h2",
						{ class: "dev-login-label", attrs: { id: "dev-login-label" } },
						this.$t("volkmann-design-code.dev-login.label"),
					),
					h("div", { class: "dev-login-buttons" }, buttons),
				]),
			]);
		},
	},
});
