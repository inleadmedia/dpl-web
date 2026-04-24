import lodash from "lodash";
import { useMemo } from "react";
import { UseQueryResult } from "react-query";
import {
  GetMaterialGloballyQuery,
  GetMaterialQuery,
  useGetMaterialGloballyQuery,
  useGetMaterialQuery,
  useGetMaterialMarc
} from "../dbc-gateway/generated/graphql";
import { WorkId } from "./types/ids";

export type WorkType = "local" | "global" | "unknown";

type DataResponse = (
  | UseQueryResult<GetMaterialQuery, unknown>
  | UseQueryResult<GetMaterialGloballyQuery, unknown>
) & { workType: WorkType };

const getData = (
  response:
    | UseQueryResult<GetMaterialQuery, unknown>
    | UseQueryResult<GetMaterialGloballyQuery, unknown>,
  type: WorkType
): DataResponse | null => {
  if (!response.isLoading && response.data?.work) {
    return { ...response, workType: type };
  }

  return null;
};

function toGroupedAbstract(abstract: string[], languages: any[]) {
  let group: any = {};

  abstract.forEach((value: string, index: number) => {
    group[languages[index]?.isoCode || "unknown"] = value;
  });

  return group;
}

function parseMarcField(workData: any, extraMarc?: string, shelfmarkOverride?: any) {
  let workPid = workData.workId.split("work-of:")[1];
  let marcSources: any = [{
    rawMarc: workData?.marc?.content,
    target: "parsedMarc"
  }, {
    rawMarc: extraMarc,
    target: "parsedExtraMarc"
  }];

  workData._abstractByLang = toGroupedAbstract(workData.abstract, workData.mainLanguages);
  let shelfmarkOverrideData: any;
  if (shelfmarkOverride && shelfmarkOverride.getter)
    shelfmarkOverrideData = shelfmarkOverride.getter(workData);

  if (workData.manifestations) {
    (workData.manifestations.all || []).forEach((manifestation: any, index: number) => {
      manifestation._abstractByLang = toGroupedAbstract(manifestation.abstract, lodash.get(manifestation, "languages.main"));

      if (shelfmarkOverrideData && manifestation.shelfmark)
        manifestation.shelfmark.shelfmark = shelfmarkOverrideData;

      if (manifestation?.marc?.content) {
        marcSources.push({
          rawMarc: manifestation.marc.content,
          target: `manifestations.all[${ index }].parsedMarc`,
          onProcessed: () => {
            if (manifestation.pid === workPid && workData.parsedExtraMarc)
              manifestation.parsedMarc = workData.parsedExtraMarc;

          }
        });
      }
    });

    ["bestRepresentation", "latest"].forEach((manifestationType) => {
      let manifestation = workData.manifestations[manifestationType];
      manifestation._abstractByLang = toGroupedAbstract(manifestation.abstract, lodash.get(manifestation, "languages.main"));

      if (shelfmarkOverrideData && manifestation.shelfmark)
        manifestation.shelfmark.shelfmark = shelfmarkOverrideData;

      if (manifestation?.marc?.content) {
        marcSources.push({
          rawMarc: manifestation.marc.content,
          target: `manifestations.${ manifestationType }.parsedMarc`,
          onProcessed: () => {
            if (manifestation.pid === workPid && workData.parsedExtraMarc)
              manifestation.parsedMarc = workData.parsedExtraMarc;

            if (shelfmarkOverrideData && manifestation.shelfmark)
              manifestation.shelfmark.shelfmark = shelfmarkOverrideData;
          }
        });
      }
    });
  }

  marcSources.forEach((datum: any) => {
    if (datum.rawMarc) {
      try {
        let marcNode = document.createElement("div");
        marcNode.innerHTML = datum.rawMarc;

        let parsedMarc: any = {};
        [].forEach.call(marcNode.querySelectorAll("[tag]"), (tagNode: HTMLElement) => {
          let tag = tagNode.getAttribute("tag") || "";
          parsedMarc[tag] = parsedMarc[tag] || {};

          [].forEach.call(tagNode.querySelectorAll("[code]"), (codeNode: HTMLElement) => {
            let code = codeNode.getAttribute("code") || "";
            parsedMarc[tag][code] = parsedMarc[tag][code] || [];
            parsedMarc[tag][code].push(codeNode.innerText);
          });
        });

        if (parsedMarc["504"] && parsedMarc["041"]) {
          let knownLanguages: string[] = Object.values(parsedMarc["041"] as string[][]).reduce((_knownLanguages: string[], list: string[]) => {
            return _knownLanguages.concat(list);
          });

          parsedMarc["504"].byLang = {};
          knownLanguages.forEach((langCode: string, index: number) => {
            parsedMarc["504"].byLang[langCode] = lodash.get(parsedMarc, `504.a[${ index }]`);
          });
        }

        lodash.set(workData, datum.target, parsedMarc);
      } catch (error) {
        console.warn("Invalid marc field XML:", error, workData);
      }
    }

    if (datum.onProcessed)
      datum.onProcessed();
  });
}

function filterDuplicates(workData: any) {
  if (!workData?.manifestations?.all)
    return;

  let manifestationsMap: any = {};

  workData.manifestations.all.forEach((manifestation: any) => {
    let [agency, faustNumber] = (manifestation.pid || "").split(":")
    agency = agency.split("-")[0];

    manifestationsMap[faustNumber] = manifestationsMap[faustNumber] || {};
    manifestationsMap[faustNumber][agency] = manifestation;
  });

  workData.manifestations.all = Object.keys(manifestationsMap).map(faustNumber => {
    let basis: any = Object.values(manifestationsMap[faustNumber]).find((manifestation: any) => {
      return manifestation.pid.includes("basis")
    });

    let katalog: any = Object.values(manifestationsMap[faustNumber]).find((manifestation: any) => {
      return manifestation.pid.includes("katalog");
    });

    /* Override the pid and marc data from the katalog to basis */
    if (basis && katalog) {
      basis.pid = katalog.pid;
      basis.marc = katalog.marc;
    }

    if (basis)
      return basis;

    return Object.values(manifestationsMap[faustNumber])[0];
  }).filter(Boolean);
}

export const useGetWork = (
  wid: WorkId,
  withExtraMarc: boolean,
  shelfmarkOverride: any
):
  | ((
      | UseQueryResult<GetMaterialQuery, unknown>
      | UseQueryResult<GetMaterialGloballyQuery, unknown>
    ) & { workType: WorkType })
  | { data: null; isLoading: true; error: null; workType: WorkType } => {
  const localWork = useGetMaterialQuery({
    wid
  });
  const globalWork = useGetMaterialGloballyQuery(
    {
      wid
    },
    { enabled: localWork.isSuccess && !localWork.data.work }
  );

  const marcId = useMemo(() => {
    let systemAgency: string = "";
    try {
      // @ts-ignore-next-line
      systemAgency = JSON.parse(document.querySelector("[data-agency-config]")?.getAttribute("data-agency-config") || "{}")?.id || "";
    } catch (error) {
      console.warn("Cannot parse agency config!", error);
    }

    let [_, materialAgency, faustNumber]: string[] = wid.split(":");
    materialAgency = materialAgency.split("-")[0];

    if (!systemAgency)
      systemAgency = materialAgency

    return [systemAgency || materialAgency, faustNumber].join(":");
  }, [wid]);


  const marcData = useGetMaterialMarc({ recordId: marcId }, { enabled: withExtraMarc || false });
  // @ts-ignore-next-line
  const extraMarc = marcData?.data?.marc?.getMarcByRecordId?.content;

  const localWorkData = getData(localWork, "local");
  if (localWorkData) {
    //filterDuplicates(localWorkData?.data?.work);
    parseMarcField(localWorkData?.data?.work, extraMarc, shelfmarkOverride);

    return localWorkData;
  }

  const globalWorkData = getData(globalWork, "global");
  if (globalWorkData) {
    //filterDuplicates(globalWorkData?.data?.work);
    parseMarcField(globalWorkData?.data?.work, extraMarc, shelfmarkOverride);

    return globalWorkData;
  }

  return { data: null, isLoading: true, error: null, workType: "unknown" };
};

export default {};
