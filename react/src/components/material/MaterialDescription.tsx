import React from "react";
import {
  getUniqueMovies,
  getDbcVerifiedSubjectsFirst,
  getLocalAgencySubjects,
  materialContainsDanish
} from "../../apps/material/helper";
import {
  constructAdvancedSearchSubjectUrl,
  constructDK5SearchUrl,
  constructMaterialUrl,
  constructSearchUrl,
  constructSubjectSearchUrl
} from "../../core/utils/helpers/url";
import { useConfig } from "../../core/utils/config";
import { useText } from "../../core/utils/text";
import { Work } from "../../core/utils/types/entities";
import { Pid, WorkId } from "../../core/utils/types/ids";
import { useUrls } from "../../core/utils/url";
import HorizontalTermLine from "../horizontal-term-line/HorizontalTermLine";
import {
  materialIsFiction,
  materialFictionNonfictionIsNotSpecified
} from "../../core/utils/helpers/general";
import SeriesList from "../card-item-list/card-list-item/series-list";
import MaterialContents from "./MaterialContents/MaterialContents";
import ButtonShare from "../button-share/button-share";

export interface MaterialDescriptionProps {
  pid: Pid;
  work: Work;
  customFields: any;
}

const MaterialDescription: React.FC<MaterialDescriptionProps> = ({ work, customFields }) => {
  const t = useText();
  const u = useUrls();
  const config = useConfig();
  const searchUrl = u("searchUrl");
  const advancedSearchUrl = u("advancedSearchUrl");
  const materialUrl = u("materialUrl");
  const {
    fictionNonfiction,
    series,
    subjects,
    relations,
    dk5MainEntry,
    manifestations
  } = work;

  const { enabled: showShareButtons } = config<{ enabled: boolean }>(
    "shareConfig",
    {
      transformer: "jsonParse"
    }
  );

  let descriptionTermFields = React.useMemo(() => {
    return Object.values(customFields || {}).map((fieldData: any) => {
      if (fieldData.label === "body")
        return;

      let values = fieldData.getter(work);

      return {
        ...fieldData,
        tags: values.filter(Boolean).map((tag: string) => {
          return {
            term: tag,
            url: new URL((fieldData.url || "#").replace(/\$\{\s*tag\s*\}/ig, tag), window.location.href)
          }
        })
      }
    }).filter(Boolean);
  }, [customFields, work]);

  let descriptionOverride = React.useMemo(() => {
    let overrideData: any = Object.values(customFields || {}).find((fieldData: any) => fieldData.label === "body");
    if (!overrideData)
      return null;

    return (overrideData.getter(work) || []).filter(Boolean).join("\n");
  }, [customFields, work]);

  const localSubjectsAgencyIds = config("localSubjectsAgencyIdsConfig", {
    transformer: "stringToArray"
  });

  const isFiction = materialIsFiction(work);

  // Show DK5 for all non-fiction works OR fiction works in non-Danish languages
  const shouldShowDk5 =
    !isFiction || (isFiction && !materialContainsDanish(work));

  const seriesMembersList =
    (series &&
      series[0]?.members.map((member) => {
        // TODO: Since the series has changed it structure and can have multiple members
        // we need to double check if we can only look at the first member entry.
        return {
          url: constructMaterialUrl(materialUrl, member.work.workId as WorkId),
          term: member.work.titles.main[0]
        };
      })) ??
    [];

  const dbcSubjects = getDbcVerifiedSubjectsFirst(subjects).map((item) => ({
    url: constructSubjectSearchUrl(searchUrl, item),
    term: item
  }));

  const localSubjects = getLocalAgencySubjects(
    manifestations.all,
    localSubjectsAgencyIds
  )
    .filter((item) => !dbcSubjects.some((dbc) => dbc.term === item))
    .map((item) => ({
      url: constructAdvancedSearchSubjectUrl(advancedSearchUrl, item),
      term: item
    }));

  const subjectsList = [...localSubjects, ...dbcSubjects];

  const filmAdaptationsList = getUniqueMovies(relations).map((item) => {
    return {
      url: constructMaterialUrl(materialUrl, item.ownerWork.workId as WorkId),
      term: item.ownerWork.titles.main[0]
    };
  });

  const fictionNonfictionList =
    fictionNonfiction && !materialFictionNonfictionIsNotSpecified(work)
      ? [
          {
            url: constructSearchUrl(searchUrl, fictionNonfiction.display),
            term: fictionNonfiction.display
          }
        ]
      : [];

  let knownFileds: any = {
    [t("inSameSeriesText")]: {
      label: t("inSameSeriesText"),
      tags: seriesMembersList,
      cy: "material-description-series-members"
    },
    [t("identifierText")]: {
      label: t("identifierText"),
      tags: subjectsList,
      cy: "material-description-identifier"
    },
    [t("fictionNonfictionText")]: {
      label: t("fictionNonfictionText"),
      tags: fictionNonfictionList,
      cy: "material-description-fiction-nonfiction"
    },
    [t("filmAdaptationsText")]: {
      label: t("filmAdaptationsText"),
      tags: filmAdaptationsList,
      cy: "material-description-film-adaptations"
    },
    [t("subjectNumberText")]: {
      label: t("subjectNumberText"),
      tags: shouldShowDk5 && dk5MainEntry ? [{
        url: constructDK5SearchUrl(searchUrl, dk5MainEntry.code),
        term: dk5MainEntry.display
      }] : []
    }
  };

  descriptionTermFields = descriptionTermFields.filter((fieldData: any) => {
    let matchedLabel = fieldData.findLabel(Object.keys(knownFileds));

    if (fieldData.hidden === true) {
      fieldData.tags = [];

      if (matchedLabel && knownFileds[matchedLabel] != null)
        knownFileds[fieldData.label].tags = [];
    }

    if (matchedLabel && knownFileds[matchedLabel] != null) {
      knownFileds[matchedLabel].tags = fieldData.merge(knownFileds[matchedLabel].tags || [], fieldData.tags || [], { outputType: "list" });

      return false;
    }

    return true;
  });

  descriptionTermFields = Object.values(knownFileds).concat(descriptionTermFields).filter(Boolean);
  const bestRepresentationContents =
    work.manifestations.bestRepresentation?.contents;

  return (
    <section className="material-description" data-cy="material-description">
      <>
        { (descriptionOverride || work.abstract && work.abstract[0]) && (
          <>
            <h2 className="material-description__heading">
              {t("descriptionHeadlineText")}
            </h2>
            <p className="material-description__content">
              { descriptionOverride === null ? work.abstract && work.abstract[0] : descriptionOverride }
            </p>
          </>
        )}
        {bestRepresentationContents && (
          <MaterialContents contents={bestRepresentationContents} />
        )}
        <div className="material-description__links mt-32">
          <SeriesList
            series={series}
            searchUrl={searchUrl}
            t={t}
            workId={work.workId}
            dataCy="material-description-series"
          />

          {
            descriptionTermFields.map((customField: any) => {
              return <HorizontalTermLine
                key={ customField.label }
                title={ customField.label }
                linkList={ customField.tags }
                dataCy={ customField.cy || "material-description-custom" }
              />
            })
          }
        </div>
        {showShareButtons && <ButtonShare className="mt-64" />}
      </>
    </section>
  );
};

export default MaterialDescription;
