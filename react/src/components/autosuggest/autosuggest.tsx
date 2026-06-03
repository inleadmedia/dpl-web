import { UseComboboxPropGetters } from "downshift";
import React, { useState } from "react";
import { createPortal } from "react-dom";
import { SuggestionsFromQueryStringQuery } from "../../core/dbc-gateway/generated/graphql";
import { Suggestion, Suggestions } from "../../core/utils/types/autosuggest";
import { useText } from "../../core/utils/text";
import AutosuggestCategory from "../autosuggest-category/autosuggest-category";
import AutosuggestMaterial from "../autosuggest-material/autosuggest-material";
import { AutosuggestText } from "../autosuggest-text/autosuggest-text";
import AutosuggestEditorial from "../autosuggest-editorial/autosuggest-editorial";

export interface AutosuggestProps {
  query?: string;
  textData: SuggestionsFromQueryStringQuery["localSuggest"]["result"];
  materialData: Suggestions;
  getMenuProps: UseComboboxPropGetters<unknown>["getMenuProps"];
  highlightedIndex: number;
  getItemProps: UseComboboxPropGetters<Suggestion>["getItemProps"];
  isOpen: boolean;
  setIsOpen: React.Dispatch<React.SetStateAction<boolean>>;
  categoryData?: SuggestionsFromQueryStringQuery["localSuggest"]["result"];
  isLoading: boolean;
  dataCy?: string;
}

const isEditorialSuggestionsEnabled = document.querySelector("[data-autosuggest-editorial]")?.getAttribute("data-autosuggest-editorial") === "true";
export const Autosuggest: React.FC<AutosuggestProps> = ({
  query,
  textData,
  materialData,
  getMenuProps,
  highlightedIndex,
  getItemProps,
  isOpen,
  setIsOpen,
  categoryData,
  isLoading,
  dataCy = "autosuggest"
}) => {
  const [editorialSuggestionsHits, setEditorialSuggestionsHits] = useState(0);
  const t = useText();

  if (isLoading && !textData) {
    return null;
  }

  return (
    <>
      {createPortal(
        <div
          aria-hidden
          className={`autosuggest-backdrop ${isOpen ? "autosuggest-backdrop--open" : ""}`}
          onClick={() => setIsOpen(false)}
        />,
        document.body
      )}

      {/* The downshift combobox works this way by design */}
      <ul
        className={`autosuggest ${isOpen ? "autosuggest--open" : ""} ${ isEditorialSuggestionsEnabled ? "autosuggest--with-editorial-suggestions" : "" }`}
        // TODO: Explicitly define prop types for better clarity
        // eslint-disable-next-line react/jsx-props-no-spreading
        {...getMenuProps()}
        style={!isOpen ? { display: "none" } : {}}
        data-cy={dataCy}
      >
        <div className="autosuggest__main-suggestions">
          <div className="autosuggest__text-suggestions">
            {
              isEditorialSuggestionsEnabled
                ? <h3 className="card-list-item__title text-header-h4 mt-8 mb-8 px-24">
                  {t("Materialer")}
                </h3>
                : null
            }

            <AutosuggestText
              textData={textData}
              highlightedIndex={highlightedIndex}
              getItemProps={getItemProps}
            />
          </div>
          {
            isEditorialSuggestionsEnabled
              ? <div className={`autosuggest__editorial-suggestions ${ editorialSuggestionsHits > 0 ? "autosuggest--found-editorial-suggestions" : "" }`}>
                {
                  editorialSuggestionsHits > 0
                    ? <h3 className="card-list-item__title text-header-h4 mt-8 mb-8 px-24">
                      {t("Redaktionelt Indhold")}
                    </h3>
                    : null
                }
                <AutosuggestEditorial query={ query } onFound={ (editorialSuggestions) => setEditorialSuggestionsHits(editorialSuggestions.total) } />
              </div>
              : null
          }
        </div>
        {materialData.length > 0 && (
          <AutosuggestMaterial
            materialData={materialData}
            getItemProps={getItemProps}
            highlightedIndex={highlightedIndex}
            textDataLength={textData.length}
          />
        )}
        {categoryData && categoryData.length > 0 && (
          <AutosuggestCategory
            categoryData={categoryData}
            getItemProps={getItemProps}
            highlightedIndex={highlightedIndex}
            textAndMaterialDataLength={textData.length + materialData.length}
          />
        )}
      </ul>
    </>
  );
};
