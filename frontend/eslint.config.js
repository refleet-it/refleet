// @ts-check
const eslint = require("@eslint/js");
const tseslint = require("typescript-eslint");
const angular = require("angular-eslint");
const eslintConfigPrettier = require("eslint-config-prettier");

module.exports = tseslint.config(
  {
    // Rendered from docs/ by scripts/build-docs.mjs. It is plain HTML, not an Angular
    // template, so the template parser trips over things Markdown legitimately contains —
    // an @scope in a package name reads as control flow, and braces in code samples as
    // interpolation.
    ignores: ["src/assets/docs/**"],
  },
  {
    files: ["**/*.ts"],
    extends: [
      eslint.configs.recommended,
      ...tseslint.configs.recommended,
      ...tseslint.configs.stylistic,
      ...angular.configs.tsRecommended,
    ],
    processor: angular.processInlineTemplates,
    rules: {
      "@angular-eslint/directive-selector": [
        "error",
        {
          type: "attribute",
          prefix: "app",
          style: "camelCase",
        },
      ],
      "@angular-eslint/component-selector": [
        "error",
        {
          type: "element",
          prefix: "app",
          style: "kebab-case",
        },
      ],
      "@typescript-eslint/no-unused-vars": [
        "error",
        {
          "argsIgnorePattern": "^_",
          "varsIgnorePattern": "^_"
        }
      ],
    },
  },
  {
    files: ["**/*.html"],
    extends: [
      ...angular.configs.templateRecommended,
      ...angular.configs.templateAccessibility,
    ],
  },
  {
    // Vendored spartan-ng UI primitives (shadcn-style "hlm" components) — not app code,
    // so they follow the library's own "hlm" selector prefix instead of "app".
    files: ["src/app/shared/ui/**/*.ts"],
    rules: {
      "@angular-eslint/directive-selector": [
        "error",
        {
          type: "attribute",
          prefix: "hlm",
          style: "camelCase",
        },
      ],
      "@angular-eslint/component-selector": [
        "error",
        {
          type: "element",
          prefix: "hlm",
          style: "kebab-case",
        },
      ],
      "@angular-eslint/no-input-rename": "off",
      "@typescript-eslint/consistent-type-definitions": "off",
    },
  },
  eslintConfigPrettier
);
