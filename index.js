/**
 * Kirby's login form plugin (`window.panel.plugins.login`): the Panel
 * renders this in place of its form. It renders Kirby's form unchanged and
 * a button per account below it (`index.php`); with the `collapse` option,
 * the accounts come first and the form opens from a button below them. Render functions instead
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
		data: () => ({ users: [], collapse: false, open: false }),
		async created() {
			try {
				({ users: this.users, collapse: this.collapse } = await this.$api.get("dev-login"));
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

			const label = (user) => (user.name === user.role ? user.name : `${user.name} (${user.role})`);

			// with descriptions (option `description`): a card per account,
			// laid out like Kirby's cardlets; else a button each
			const cards = this.users.some((user) => "description" in user);

			const accounts = this.users.map((user) =>
				cards
					? h(
							"div",
							{
								class: "dev-login-card",
								attrs: { role: "button", tabindex: 0, title: user.email },
								on: {
									click: () => this.login(user.email),
									keydown: (event) => {
										if (event.key === "Enter" || event.key === " ") {
											event.preventDefault();
											this.login(user.email);
										}
									},
								},
							},
							[
								h("k-item", {
									props: {
										image: { icon: "user", back: "var(--panel-color-back)", color: "gray" },
										info: user.description,
										layout: "cardlets",
										text: label(user),
									},
								}),
							],
						)
					: h("k-button", {
							props: {
								icon: "user",
								size: "sm",
								text: label(user),
								title: user.email,
								variant: "filled",
							},
							on: { click: () => this.login(user.email) },
						}),
			);

			const nav = h(
				"nav",
				{ class: "dev-login", attrs: { "aria-labelledby": "dev-login-label", "data-collapse": this.collapse } },
				[
					h(
						"h2",
						{ class: "dev-login-label", attrs: { id: "dev-login-label" } },
						this.$t("volkmann-design-code.dev-login.label"),
					),
					cards
						? h("div", { class: "k-items dev-login-cards", attrs: { "data-layout": "cardlets" } }, accounts)
						: h("div", { class: "dev-login-buttons" }, accounts),
				],
			);

			if (this.collapse !== true) {
				return h("div", [form, nav]);
			}

			// Kirby's form behind a button, below the accounts
			return h("div", [
				nav,
				h("div", { class: "dev-login-form" }, [
					this.open
						? form
						: h("k-button", {
								props: {
									icon: "email",
									size: "sm",
									text: this.$t("volkmann-design-code.dev-login.form"),
									variant: "filled",
								},
								on: { click: () => (this.open = true) },
							}),
				]),
			]);
		},
	},
});
