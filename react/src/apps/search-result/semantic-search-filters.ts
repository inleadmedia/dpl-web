import { FacetResult } from "../../core/dbc-gateway/generated/graphql";
import { FacetState, resolveFilterFieldName } from "./helpers";

export type SemanticSearchFilters = {
  material_type?: string;
  language?: string;
  children?: boolean;
};

const CHILDREN_FACET_VALUES = ["børnematerialer", "til børn"];

/**
 * Maps URL facet state to semantic search REST query parameters.
 *
 * Only filters supported by the vector index payload are included.
 */
export const buildSemanticSearchFilters = (
  facetsFromUrl: FacetState[],
  facetResults: FacetResult[]
): SemanticSearchFilters => {
  const filters: SemanticSearchFilters = {};

  facetsFromUrl.forEach(({ facetName, selectedValues }) => {
    const value = selectedValues[0];
    if (!value) {
      return;
    }

    const normalizedFacetName = facetName.toLowerCase();
    const facet = facetResults.find(
      (item) => resolveFilterFieldName(item.name).toLowerCase() === normalizedFacetName
    );

    if (normalizedFacetName === "mainlanguages" && facet) {
      const match = facet.values.find(
        (facetValue) => facetValue.term === value || facetValue.key === value
      );
      if (match) {
        filters.language = match.key;
      }
      return;
    }

    if (normalizedFacetName === "materialtypesgeneral") {
      filters.material_type = value.toUpperCase();
      return;
    }

    if (normalizedFacetName === "childrenoradults") {
      if (CHILDREN_FACET_VALUES.some((needle) => value.toLowerCase().includes(needle))) {
        filters.children = true;
      }
    }
  });

  return filters;
};

export default buildSemanticSearchFilters;
