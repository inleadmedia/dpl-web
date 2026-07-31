/* eslint-disable */
(function() {
  if (window.InleadReactInjector != null)
    return;

  class ReactInjector {
    constructor() {
      this._reactPackage;
      this._instantiatedInjections = [];
      this._packagesList = {};
      this._unwrappedPackages = {};
    }

    getUnwrappedPackage(key) {
      return this._unwrappedPackages[key];
    }

    unwrapPackages(unwrapPackageMethod) {
      Object.keys(this._packagesList).forEach(packageKey => {
        this.unwrapPackage(this._packagesList[packageKey], unwrapPackageMethod);
      });
    }

    unwrapPackage(_package, unwrapPackageMethod) {
      if (!_package || typeof _package !== "object")
        return;

      /* Clone of the _package is required for dev mode */
      if ("__STORYBOOK_STORY_STORE__" in window)
        _package = Object.assign({}, _package);

      Object.keys(_package).forEach(minifiedKey => {
        if (!_package[minifiedKey] || !_package[minifiedKey].toString)
          return;

        let unwrapped = unwrapPackageMethod(_package[minifiedKey].toString());
        if (unwrapped) {
          _package[unwrapped.methodKey] = _package[minifiedKey];
          _package[unwrapped.methodKey].minifiedKey = minifiedKey;

          this._unwrappedPackages[unwrapped.packageKey] = _package;
        }
      });
    }

    findPackage(validator) {
      let foundPackageKey = Object.keys(this._packagesList).find(packageKey => {
        return this._packagesList[packageKey]
          && typeof this._packagesList[packageKey] === "object"
          && validator(this._packagesList[packageKey]);
      });

      if (foundPackageKey)
        return this._packagesList[foundPackageKey];
    }

    setInjectionToReactLibrary(reactPackage) {
      // Do not apply injection handler twice
      if (this._reactPackage === reactPackage)
        return;

      // Remove injection from old package while re-initialization
      if (this?._reactPackage?.createElement?._originalCreateElement) {
        this._reactPackage.createElement = this._reactPackage.createElement._originalCreateElement;
      }

      this._reactPackage = reactPackage;

      // Hook to the react createElement function to be possible to hook into templates from outside of main bundle.
      var originalCreateElement = reactPackage.createElement;

      var that = this;
      reactPackage.createElement = function(tag, props) {
        var children = [].slice.call(arguments, 2);
        var updated = that.applyPossibleInjections({ tag, props, children });

        tag = updated.tag;
        props = updated.props;
        children = updated.children;

        return originalCreateElement.apply(this, [tag, props].concat(children));
      };

      reactPackage.createElement._originalCreateElement = originalCreateElement;
    }

    _matchElement(reactNodeData, condition) {
      if (reactNodeData == null)
        return false;

      return Object.keys(condition).every(conditionKey => {
        if (reactNodeData.props == null || reactNodeData.props[conditionKey] == null)
          return false;

        let valueToValidate = reactNodeData.props[conditionKey];
        if (conditionKey === "className" && typeof valueToValidate === "string") {
          valueToValidate = valueToValidate.split(" ");

          return valueToValidate.includes(condition[conditionKey]);
        }

        return valueToValidate === condition[conditionKey];
      });
    }

    // Instantiate injection
    add(injection) {
      this._instantiatedInjections.push({
        options: injection.options,
        validate: (reactNodeData) => {
          if (reactNodeData == null)
            return;

          if (injection.options.onTargetValidation)
            injection.options.onTargetValidation(reactNodeData);

          return this._matchElement(reactNodeData, injection.options.condition);
        },
        reactComponent: injection.handler(this._reactPackage)
      });
    }

    applyPossibleInjections(reactNodeData) {
      for (let injection of this._instantiatedInjections) {
        if (injection.validate(reactNodeData) === false)
          continue;

        const injectionType = injection.options.injectionType || "append";
        reactNodeData.children = Array.isArray(reactNodeData.children) ? reactNodeData.children : [reactNodeData.children];

        const injectionNode = this._reactPackage.createElement(injection.reactComponent);
        if (injectionType === "append") {
          reactNodeData.children = reactNodeData.children.concat([injectionNode]);
        } else if (injectionType === "prepend") {
          reactNodeData.children = [injectionNode].concat(reactNodeData.children);
        } else if (typeof injectionType === "object") {
          const childIndex = reactNodeData.children.findIndex((reactChildData) => {
            return this._matchElement(reactChildData, injectionType.beforeChild || injectionType.afterChild);
          });

          if (childIndex === -1) {
            console.warn("Child not found to injection, node will be added at the end of children list!");
            reactNodeData.children = reactNodeData.children.concat([injectionNode]);
            continue;
          }

          reactNodeData.children = reactNodeData.children.slice(0);
          if ("beforeChild" in injectionType) {
            reactNodeData.children.splice(childIndex, 0, injectionNode);
          } else if ("afterChild" in injectionType) {
            reactNodeData.children.splice(childIndex + 1, 0, injectionNode);
          } else {
            console.warn("Unknown injection type!", injection);
          }
        }
      }

      return reactNodeData;
    }

    clearInjections() {
      this._instantiatedInjections = [];
    }
  }

  window.InleadReactInjector = new ReactInjector();

  function InjectToWebpackBundle() {
    globalThis.webpackChunk_danskernesdigitalebibliotek_dpl_react.push([
      ["inlead_hook"], {
        inlead_hook(e, a, _import) {
          // Search for react package at webpackChunks

          globalThis.webpackChunk_danskernesdigitalebibliotek_dpl_react.forEach((bundle) => {
            Object.keys(bundle[1]).forEach((packageKey) => {
              var _package = _import(packageKey);
              if (!_package)
                return;

              window.InleadReactInjector._packagesList[packageKey] = _package;
            });
          });

          var reactPackage = window.InleadReactInjector.findPackage((_package) => {
            // Validate package exports to make sure that the react package
            return _package.createElement && _package.useMemo && (_package.Fragment || "").toString().includes("react.");
          });

          if (!reactPackage)
            return console.warn("React package not found! The external injection isn't possible!");

          window.InleadReactInjector.setInjectionToReactLibrary(reactPackage);
        }
      },
      e => {
        e.O(0, [], () => e(e.s = "inlead_hook"));
        e.O();
      }
    ]);
  }

  if (globalThis.webpackChunk_danskernesdigitalebibliotek_dpl_react)
    InjectToWebpackBundle();
})();
/* eslint-enable */
