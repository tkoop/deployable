import { defineConfig } from "vite";
import tailwindcss from "@tailwindcss/vite";

/*
 * Deployable's CSS and JS compile to the same public/css/app.css and
 * public/js/app.js files the blade templates have always linked to through
 * asset(), so nothing about the deployment contract changes: no manifest, no
 * public/build directory, and the committed static assets keep working.
 */
export default defineConfig({
	plugins: [
		tailwindcss(),
	],
	build: {
		outDir: "public",
		emptyOutDir: false,
		manifest: false,
		publicDir: false,
		rollupOptions: {
			input: "resources/js/app.js",
			output: {
				entryFileNames: "js/app.js",
				assetFileNames: (assetInfo) => {
					if (assetInfo.names.some((name) => name.endsWith(".css"))) {
						return "css/app.css";
					}
					return "assets/[name][extname]";
				},
			},
		},
	},
});