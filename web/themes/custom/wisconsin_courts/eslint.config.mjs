import path from 'node:path';

import { includeIgnoreFile } from '@eslint/compat';
import js from '@eslint/js';
import { configs, plugins } from 'eslint-config-airbnb-extended';
import { rules as prettierConfigRules } from 'eslint-config-prettier';
import prettierPlugin from 'eslint-plugin-prettier';
import ymlPlugin from 'eslint-plugin-yml';
import globals from 'globals';

// Plugins that need to be explicitly loaded
import importPlugin from 'eslint-plugin-import';

const gitignorePath = path.resolve('.', '.gitignore');

const jsConfig = [
  // ESLint Recommended Rules
  {
    name: 'js/config',
    ...js.configs.recommended,
  },
  // Stylistic Plugin
  plugins.stylistic,
  // Import X Plugin
  plugins.importX,
  // Airbnb Base Recommended Config
  ...configs.base.recommended,
];

const nodeConfig = [
  // Node Plugin
  plugins.node,
  // Airbnb Node Recommended Config
  ...configs.node.recommended,
];

const prettierConfig = [
  // Prettier Plugin
  {
    name: 'prettier/plugin/config',
    plugins: {
      prettier: prettierPlugin,
    },
  },
  // Prettier Config
  {
    name: 'prettier/config',
    rules: {
      ...prettierConfigRules,
      'prettier/prettier': 'error',
    },
  },
];

// YAML config
const yamlConfig = [
  {
    files: ['**/*.yml', '**/*.yaml'],
    plugins: {
      yml: ymlPlugin,
    },
    rules: {
      'yml/no-empty-document': 'error',
      'yml/quotes': ['error', { prefer: 'single' }],
      'yml/indent': ['error', 2],
    },
  },
];

// Custom rules with explicit import plugin
const customRulesConfig = {
  plugins: {
    import: importPlugin,
  },
  rules: {
    'consistent-return': ['off'],
    'no-underscore-dangle': ['off'],
    'max-nested-callbacks': ['warn', 3],
    'import/no-mutable-exports': ['warn'],
    'no-plusplus': ['warn', { allowForLoopAfterthoughts: true }],
    'no-param-reassign': ['off'],
    'no-prototype-builtins': ['off'],
    'no-unused-vars': ['warn'],
    'operator-linebreak': [
      'error',
      'after',
      {
        overrides: {
          '?': 'ignore',
          ':': 'ignore',
        },
      },
    ],
  },
};

export default [
  // Global ignores
  {
    ignores: [
      '**/gulpfile.js',
      '**/node_modules/**',
      '**/assets/**',
      '**/*.yml',
    ],
  },

  // .gitignore
  includeIgnoreFile(gitignorePath),

  // Globals
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
        Drupal: 'readonly',
        drupalSettings: 'readonly',
        drupalTranslations: 'readonly',
        jQuery: 'readonly',
        _: 'readonly',
        Cookies: 'readonly',
        Backbone: 'readonly',
        Modernizr: 'readonly',
        loadjs: 'readonly',
        Shepherd: 'readonly',
        Sortable: 'readonly',
        once: 'readonly',
        CKEditor5: 'readonly',
        tabbable: 'readonly',
      },
    },
  },

  // JS / Airbnb
  ...jsConfig,

  // Node
  ...nodeConfig,

  // Prettier
  ...prettierConfig,

  // YAML
  ...yamlConfig,

  // Custom rules override the inherited presets.
  customRulesConfig,
  // Drupal SDC scripts are classic scripts, not ES modules.
  {
    files: ['components/**/*.js'],
    languageOptions: { sourceType: 'script' },
    rules: { strict: ['error', 'function'] },
  },
  // Build checks run once in Node; synchronous traversal is intentional here.
  {
    files: ['scripts/**/*.cjs'],
    rules: {
      'n/no-sync': 'off',
      'no-console': 'off',
      'no-restricted-syntax': 'off',
      'import-x/no-extraneous-dependencies': [
        'error',
        { devDependencies: true },
      ],
    },
  },
];
