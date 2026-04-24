import React, { useEffect, useMemo } from "react";
import languageIcon from "../../../components/search-bar/icon/language-icon.svg";

const googleTranslateElementInit = () => {
  // eslint-disable-next-line no-new
  let labelElement = document.querySelector("#google-translate-element-label");
  let container = document.querySelector("#google-translate-element");
  new window.google.translate.TranslateElement(
    {
      pageLanguage: "da",
      includedLanguages: "en,de,nl,bg,ro,bs,hr,cs,et,el,tr,ku,ckb,ur,th,vi,zh-CN,zh-TW,yue,tl,sw,uk,ru,no,sv,fi,is,kl,fo,kl,fr,es,it,pt,pl,ka,lt,lv",
      autoDisplay: false
    },
    container
  );

  let googleTranslateSelectElement = document.querySelector("#google-translate-element select");
  let getActiveSelectText = () => {
    // @ts-ignore-next-line
    let selectedOptionIndex = googleTranslateSelectElement.selectedIndex;
    if (selectedOptionIndex === -1)
      return "";

    // @ts-ignore-next-line
    return googleTranslateSelectElement.options[selectedOptionIndex].text;
  };

  if (googleTranslateSelectElement) {
    // @ts-ignore-next-line
    googleTranslateSelectElement.onchange = function() {
      // @ts-ignore-next-line
      labelElement.innerText = getActiveSelectText();
    };
    googleTranslateSelectElement.setAttribute("id", container?.getAttribute("id") || "");
    googleTranslateSelectElement.className += " " + container?.className;

    container?.replaceWith(googleTranslateSelectElement);
  }

};

const Translations: React.FC = () => {
  const enabledTranslations = useMemo(() => {
    let _enabledTranslations: any = {
      google: true,
      drupal: false
    };

    const _enabledControls = (document.querySelector("[data-eonext-translation-type]")?.getAttribute("data-eonext-translation-type") || "").split(",").map(controlType => {
      return controlType.trim().split("_")[0];
    }).filter(Boolean);

    if (_enabledControls.length !== 0)
      _enabledTranslations.google = false;

    _enabledControls.forEach(controlType => {
      _enabledTranslations[controlType] = true;
    });

    if (_enabledTranslations.drupal) {
      try {
        const drupalLanguages = JSON.parse(document.querySelector("[data-eonext-translation-languages]")?.getAttribute("data-eonext-translation-languages") || "[]");
        if (drupalLanguages.length === 0) {
          _enabledTranslations.drupal = false;
        } else {
          let useDefaultLanguage = true;
          const processedDrupalLanguages = Object.keys(drupalLanguages).map(languageCode => {
            const pathCode = drupalLanguages[languageCode].path.split("/").filter(Boolean)[0];
            const isActiveLanguage = window.location.pathname.startsWith(pathCode);
            if (isActiveLanguage)
              useDefaultLanguage = false;

            return Object.assign({}, drupalLanguages[languageCode], {
              code: languageCode,
              icon: "/modules/custom/eonext_translation/assets/flags/" + languageCode + ".png",
              active: isActiveLanguage
            });
          });

          if (useDefaultLanguage)
            processedDrupalLanguages[0].active = true;

          _enabledTranslations.drupal = {
            active: processedDrupalLanguages.find(languageOptions => languageOptions.active),
            list: processedDrupalLanguages,
            onChange: function(value: any) {
              if (_enabledTranslations.drupal.active.path === value.target.value)
                return;

              window.location.href = value.target.value;
            }
          };
        }
      } catch (error) {
        console.warn("Cannot parse drupal translation languages! ([data-eonext-translation-languages])", error);
      }
    }

    return _enabledTranslations;
  }, []);

  useEffect(() => {
    if (!enabledTranslations.google)
      return;

    if (window.google?.translate?.TranslateElement)
      return googleTranslateElementInit();

    const addScript = document.createElement("script");
    addScript.setAttribute("src", "//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit");
    document.body.appendChild(addScript);

    window.googleTranslateElementInit = googleTranslateElementInit;
  }, []);

  return (
    <div className="header__translations">
      <div className={[
        "header__translations-wrapper header__button",
        enabledTranslations.google && enabledTranslations.drupal ? "header__translations-wrapper--extended" : ""
      ].filter(Boolean).join(" ")}>
        {
          enabledTranslations.google
            ? <label className="header__translations-button">
              <img
                className="header__translations-icon"
                src={languageIcon}
                alt="language icon"
              />
              <span className="header__translations-label" id="google-translate-element-label">Vælg sprog</span>
              <div className="header__translations-select" id="google-translate-element" />
            </label>
            : null
        }

        {
          enabledTranslations.drupal
            ? <label className="header__translations-button">
              <img
                className="header__translations-icon"
                src={enabledTranslations.drupal.active.icon}
                alt={enabledTranslations.drupal.active.name}
              />
              <span className="header__translations-label">
                { enabledTranslations.drupal.active.name }
              </span>
              <select className="header__translations-select" onChange={ enabledTranslations.drupal.onChange }>
                {
                  enabledTranslations.drupal.list.map((languageOptions: any) => {
                    return <option id={languageOptions.code} value={languageOptions.path}>
                      {languageOptions.name}
                    </option>
                  })
                }
              </select>
            </label>
            : null
        }
      </div>
    </div>
  );
};

export default Translations;
