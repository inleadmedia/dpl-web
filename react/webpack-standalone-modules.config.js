//webpack-standalone-modules.config.js

const path = require("path");
const webpack = require("webpack");
const VersionFile = require("webpack-version-file-plugin");
const { EnvironmentPlugin } = require("webpack");
const ESLintPlugin = require("eslint-webpack-plugin");
const { getWebPackEnvVariables } = require("./webpack.helpers");

module.exports = (_env, argv) => {
  const production = argv.mode === "production";

  const plugins = [
    new EnvironmentPlugin({
      NODE_ENV: "development"
    }),
    new ESLintPlugin({
      files: ["*.js", "*.jsx", "*.ts", "*.tsx"],
      context: path.resolve(__dirname, "./src")
    })
  ];

  // Add environment variables to webpack in development mode
  if (!production) {
    const variables = getWebPackEnvVariables();
    if (variables) {
      plugins.push(new webpack.DefinePlugin(variables));
    }
  }

  return {
    entry: {
      "search-result-autosuggest-editorial-injection": "./src/apps/search-result/SearchResultAutosuggestEditorialInjection.jsx"
    },
    output: {
      filename: "[name].js",
      path: path.resolve(__dirname, "dist-standalone-modules")
    },
    mode: argv.mode,
    devtool: production ? "source-map" : "inline-source-map",
    optimization: {
      runtimeChunk: false,
      splitChunks: false,
      // Enable tree-shaking to remove unused Lodash methods
      usedExports: true
    },
    resolve: {
      extensions: [".js", ".jsx", ".tsx", ".ts", ".json"]
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx|ts|tsx)$/,
          exclude: /node_modules/,
          use: ["babel-loader"]
        },
        // We consume css and svg files from dpl-design-system package
        {
          test: /\.css$/,
          use: ["style-loader", "css-loader"]
        },
        {
          test: /\.svg$/,
          use: [
            {
              loader: "svg-url-loader"
            }
          ]
        }
      ]
    },
    stats: {
      assets: true,
      chunks: true,
      modules: true
    },
    plugins
  };
};
