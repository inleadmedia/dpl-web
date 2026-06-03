import { QueryFunctionContext } from "react-query";
import FetchFailedCriticalError from "../fetchers/FetchFailedCriticalError";
import { getToken, TOKEN_LIBRARY_KEY, TOKEN_USER_KEY } from "../token";
import DbcGateWayHttpError from "./DbcGateWayHttpError";
import { getQueryUrlFromContext } from "./helper";

const forceLibraryToken = document.querySelector("[data-lms-user-api-enabled]")?.getAttribute("data-lms-user-api-enabled") === "true";
export const fetcher = <TData, TVariables>(
  query: string,
  variables?: TVariables,
  abortController?: AbortController,
  urlOverride?: string
) => {
  return (context?: QueryFunctionContext): Promise<TData> => {
    // Resolve the url based on the query name if present.
    const url = urlOverride ?? getQueryUrlFromContext(context);

    // The whole concept of agency id, profile and and bearer token needs to be refined.
    // First version is with a library token.
    let token = getToken(TOKEN_LIBRARY_KEY);
    if (forceLibraryToken !== true)
      token = getToken(TOKEN_USER_KEY) || token;

    const authHeaders = token ? ({ Authorization: `Bearer ${token}` } as object) : {};

    return fetch(url, {
      method: "POST",
      signal: abortController?.signal,
      headers: {
        "Content-Type": "application/json",
        ...authHeaders
      },
      body: JSON.stringify({ query, variables })
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new DbcGateWayHttpError(response.status, response.statusText);
        }

        const json = await response.json();

        // See if we have any errors in the response.
        if (json.errors) {
          const { message } = json.errors[0];

          throw new Error(message);
        }

        return json.data;
      })
      .catch((error: unknown) => {
        if (error instanceof DbcGateWayHttpError) {
          throw error;
        }

        const message =
          error instanceof Error ? error.message : "Unknown error";
        throw new FetchFailedCriticalError(message, query);
      });
  };
};

export default {};
