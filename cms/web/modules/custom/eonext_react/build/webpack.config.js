// Shared toolchain for the standalone React injection bundles that EO Next
// modules ship. It lives here rather than in each module so the webpack, babel
// and core-js versions are defined once.
//
// Usage, from this directory:
//   npm ci
//   npm run build -- --env module=eonext_quickloan
//
// The target module declares what to build in js/src/entries.json, mapping the
// output bundle name to a source file. Output is written to the module's
// js/dist.
//
// Paths are all resolved against this directory on purpose: the sources being
// compiled live in another module, so babel's and webpack's usual "search
// upwards from the file" lookups would not find this config or node_modules.

const path = require("path");
const fs = require("fs");

const MODULES_DIR = path.resolve(__dirname, "..", "..");
const NODE_MODULES = path.join(__dirname, "node_modules");

module.exports = (env, argv) => {
  const moduleName = env && env.module;
  if (!moduleName) {
    throw new Error(
      "No module to build. Use: npm run build -- --env module=<module_name>"
    );
  }

  const moduleDir = path.join(MODULES_DIR, moduleName);
  if (!fs.existsSync(moduleDir)) {
    throw new Error(`Module ${moduleName} not found in ${MODULES_DIR}.`);
  }

  const srcDir = path.join(moduleDir, "js", "src");
  const entriesFile = path.join(srcDir, "entries.json");
  if (!fs.existsSync(entriesFile)) {
    throw new Error(
      `${moduleName} has no js/src/entries.json declaring what to build.`
    );
  }

  const entry = Object.fromEntries(
    Object.entries(JSON.parse(fs.readFileSync(entriesFile, "utf8"))).map(
      ([name, file]) => [name, path.join(srcDir, file)]
    )
  );

  return {
    context: moduleDir,
    entry,
    output: {
      filename: "[name].js",
      path: path.join(moduleDir, "js", "dist")
    },
    mode: argv.mode,
    devtool: "source-map",
    optimization: {
      runtimeChunk: false,
      splitChunks: false,
      usedExports: true
    },
    resolve: {
      extensions: [".js", ".jsx"],
      modules: [NODE_MODULES, "node_modules"]
    },
    resolveLoader: {
      modules: [NODE_MODULES, "node_modules"]
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: [
            {
              loader: "babel-loader",
              options: {
                babelrc: false,
                configFile: path.join(__dirname, "babel.config.json")
              }
            }
          ]
        }
      ]
    }
  };
};
