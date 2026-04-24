import { useMemo } from "react";
import {
  useQueryState,
  parseAsJson,
  parseAsBoolean,
  parseAsStringEnum,
  parseAsString
} from "nuqs";
import { FilterState, FacetState, SortOption } from "../types";
import { buildCQLQuery, isWildcardQuery } from "../lib/query-builder";
import { isValidFilterState, isValidFacetState } from "../lib/validation";
import { Branch, useCMSBranches } from "../../advanced-search/AdvancedSearchBranchSelect";

interface UseSearchQueriesReturn {
  cql: string;
  isSearchEnabled: boolean;
  onShelf: boolean;
  onlyExtraTitles: boolean;
  sort: SortOption;
  setSort: (sort: SortOption) => void;
  urlState: {
    branch: Branch,
    filters: FilterState[];
    preSearchFacets: FacetState[];
    facets: FacetState[];
  };
}

/**
 * Hook to read search parameters from URL and build queries
 */
export const useSearchQueries = (): UseSearchQueriesReturn => {
  const branches = useCMSBranches();

  // Read all search state from URL
  const [filters] = useQueryState(
    "filters",
    parseAsJson((value) => {
      if (isValidFilterState(value)) return value;
      return [];
    }).withDefault([])
  );

  const [branchId] = useQueryState("branchId", parseAsString.withDefault(""));
  let branch = { branchId: branchId, title: "" };
  if (branch.branchId) {
    let knownBranch = branches.find((_branch: Branch) => {
      return _branch.branchId === branch.branchId;
    });

    if (knownBranch)
      branch = knownBranch;
  }

  const [preSearchFacets] = useQueryState(
    "preSearchFacets",
    parseAsJson((value) => {
      if (isValidFacetState(value)) return value;
      return [];
    }).withDefault([])
  );

  const [facets] = useQueryState(
    "facets",
    parseAsJson((value) => {
      if (isValidFacetState(value)) return value;
      return [];
    }).withDefault([])
  );

  // Read toggle states
  const [onShelf] = useQueryState("onShelf", parseAsBoolean.withDefault(false));

  const [onlyExtraTitles] = useQueryState(
    "onlyExtraTitles",
    parseAsBoolean.withDefault(false)
  );

  const [sort, setSort] = useQueryState(
    "sort",
    parseAsStringEnum<SortOption>(Object.values(SortOption)).withDefault(
      SortOption.Relevance
    )
  );

  // Radio button filter states
  const [accessType] = useQueryState("accessType", parseAsString);
  const [fictionType] = useQueryState("fictionType", parseAsString);
  const [ageGroup] = useQueryState("ageGroup", parseAsString);

  // Build CQL query from all inputs
  const cql = useMemo(
    () =>
      buildCQLQuery(
        filters,
        preSearchFacets,
        facets,
        onlyExtraTitles,
        accessType,
        fictionType,
        ageGroup
      ),
    [
      filters,
      preSearchFacets,
      facets,
      onlyExtraTitles,
      accessType,
      fictionType,
      ageGroup
    ]
  );

  const isSearchEnabled = !isWildcardQuery(cql);

  return {
    cql,
    isSearchEnabled,
    onShelf,
    onlyExtraTitles,
    sort,
    setSort,
    urlState: {
      filters,
      branch,
      preSearchFacets,
      facets
    }
  };
};
