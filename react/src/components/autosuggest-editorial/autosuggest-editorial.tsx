import React, { useRef, useState, useEffect } from "react";
import { getServiceBaseUrl, serviceUrlKeys } from "../../core/utils/reduxMiddleware/extractServiceBaseUrls";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";
import { formatCustomDateString } from "../../core/utils/helpers/date";
import { useText } from "../../core/utils/text";
import { omitBy } from "lodash";

import AutosuggestEditorialCostructor from "../../components/autosuggest-editorial/autosuggest-editorial-constructor.jsx";

interface AutosuggestEditorialProps {
  query?: string;
  minQueryLimit?: number;
  limit?: number;
  template?: string;
  materialId?: string;
  filter?: string;
  onFound?(searchResult: EditorialSearchResult): void;
};

interface EditorialSearchResult {
  total: number;
  page: number;
  page_size: number;
  results: EditorialSuggestion;
}

interface EditorialSuggestion {
  uuid: string;
  bundle: string;
  title: string;
  url: string;
  created_at: string;
  image: {
    url: string;
    alt: string;
  };
  categories?: string[];
  teaser_text?: string;
};

export default AutosuggestEditorialCostructor(React, {
  useRef,
  useState,
  useEffect,
  getServiceBaseUrl,
  serviceUrlKeys,
  getServiceUrlWithParams,
  formatCustomDateString,
  useText,
  omitBy
}) as React.FC<AutosuggestEditorialProps>;
