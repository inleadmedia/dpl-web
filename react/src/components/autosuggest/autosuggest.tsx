import { UseComboboxPropGetters } from "downshift";
import React from "react";
import { SuggestionsFromQueryStringQuery } from "../../core/dbc-gateway/generated/graphql";
import { Suggestion, Suggestions } from "../../core/utils/types/autosuggest";
import AutosuggestCategory from "../autosuggest-category/autosuggest-category";
import AutosuggestMaterial from "../autosuggest-material/autosuggest-material";
import { AutosuggestText } from "../autosuggest-text/autosuggest-text";
import AutosuggestEditorial from "../autosuggest-editorial/autosuggest-editorial";
import { createPortal } from "react-dom";

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
            <AutosuggestText
              textData={textData}
              highlightedIndex={highlightedIndex}
              getItemProps={getItemProps}
            />
          </div>
          {
            isEditorialSuggestionsEnabled
              ? <div className="autosuggest__editorial-suggestions">
                <AutosuggestEditorial query={ query } />
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
