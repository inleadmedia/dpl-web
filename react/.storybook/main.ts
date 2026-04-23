const config = {
  env: (config) => {
    let _env = { ...config };
    [
      "CMS_BASEURL",
      "WAYFINDER_BASEURL",
      "PUBLIZON_BASEURL",
      "FBS_BASEURL",
      "GRAPHQL_API_BASEURL",
      "COVERS_BASEURL",
      "USE_DEVELOPMENT_OPTIONS"
    ].forEach(key => {
      if (process.env[key] != null)
        _env[key] = process.env[key];
    });

    return _env;
  },
  stories: ["../src/**/*.stories.@(js|jsx|mjs|ts|tsx)"],

  addons: [
    "@storybook/addon-essentials",
    "@storybook/addon-queryparams",
    "@storybook/addon-webpack5-compiler-babel",
    "@chromatic-com/storybook"
  ],

  typescript: {
    check: true,
    checkOptions: {},
    reactDocgen: "react-docgen-typescript",
    reactDocgenTypescriptOptions: {
      shouldExtractLiteralValuesFromEnum: true,
      propFilter: (prop) =>
        prop.parent ? !/node_modules/.test(prop.parent.fileName) : true
    }
  },

  framework: {
    name: "@storybook/react-webpack5",
    options: {}
  },

  docs: {
    autodocs: "tag"
  },

  staticDirs: [{ from: "../public", to: "/modules/custom/eonext_translation/assets" }]
};

export default config;
