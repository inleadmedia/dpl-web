import { useQuery } from "react-query";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";
import {
  getServiceBaseUrl,
  serviceUrlKeys
} from "../../core/utils/reduxMiddleware/extractServiceBaseUrls";
import { Work } from "../../core/utils/types/entities";
import { SemanticSearchFilters } from "./semantic-search-filters";

export const SEMANTIC_SEARCH_MAX_LIMIT = 50;

export type MaterialSemanticSearchResult = {
  workId: string;
  score: number;
  work: Work;
};

export type MaterialSemanticSearchResponse = {
  hitcount: number;
  results: MaterialSemanticSearchResult[];
};

type UseMaterialSemanticSearchArgs = {
  q: string;
  limit?: number;
  filters?: SemanticSearchFilters;
  enabled?: boolean;
};

export const useMaterialSemanticSearch = ({
  q,
  limit = SEMANTIC_SEARCH_MAX_LIMIT,
  filters = {},
  enabled = true
}: UseMaterialSemanticSearchArgs) => {
  return useQuery(
    ["material-semantic-search", q, limit, filters],
    async (): Promise<MaterialSemanticSearchResponse> => {
      const params: Record<string, string | number> = {
        q,
        limit
      };

      if (filters.material_type) {
        params.material_type = filters.material_type;
      }
      if (filters.language) {
        params.language = filters.language;
      }
      if (filters.children) {
        params.children = 1;
      }

      const serviceUrl = getServiceUrlWithParams({
        baseUrl: getServiceBaseUrl(serviceUrlKeys.dplCms),
        url: "/api/v1/material-semantic-search",
        params
      });

      const response = await window.fetch(serviceUrl, { mode: "cors" });
      if (!response.ok) {
        throw new Error(
          `Semantic search failed with status ${response.status}`
        );
      }

      return response.json();
    },
    {
      enabled: enabled && q.trim().length > 0,
      keepPreviousData: true
    }
  );
};

export default useMaterialSemanticSearch;
