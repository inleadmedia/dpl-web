import lodash from "lodash";
import CreateIcon from "@danskernesdigitalebibliotek/dpl-design-system/build/icons/collection/Create.svg";
import Receipt from "@danskernesdigitalebibliotek/dpl-design-system/build/icons/collection/Receipt.svg";
import VariousIcon from "@danskernesdigitalebibliotek/dpl-design-system/build/icons/collection/Various.svg";
import React, { useEffect, useState, useMemo } from "react";
import { useDeepCompareEffect, useUpdateEffect } from "react-use";
import DisclosureControllable from "../../components/Disclosures/DisclosureControllable";
import DisclosureSummary from "../../components/Disclosures/DisclosureSummary";
import DigitalModal from "../../components/material/digital-modal/DigitalModal";
import InfomediaModal from "../../components/material/infomedia/InfomediaModal";
import {
  hasCorrectAccess,
  hasCorrectAccessType
} from "../../components/material/material-buttons/helper";
import MaterialAdditionalDescription from "../../components/material/MaterialAdditionalDescription";
import MaterialDescription from "../../components/material/MaterialDescription";
import MaterialDetailsList from "../../components/material/MaterialDetailsList";
import MaterialHeader from "../../components/material/MaterialHeader";
import MaterialMainfestationItem from "../../components/material/MaterialMainfestationItem";
import { MaterialReviews } from "../../components/material/MaterialReviews";
import MaterialSkeleton from "../../components/material/MaterialSkeleton";
import { PeriodicalEdition } from "../../components/material/periodical/helper";
import { statistics } from "../../core/statistics/statistics";
import {
  useCollectPageStatistics,
  usePageStatistics
} from "../../core/statistics/useStatistics";
import { getAllFaustIds, getWorkPid } from "../../core/utils/helpers/general";
import {
  getUrlQueryParam,
  setQueryParametersInUrl
} from "../../core/utils/helpers/url";
import { usePatronData } from "../../core/utils/helpers/usePatronData";
import { isAnonymous, isBlocked } from "../../core/utils/helpers/user";
import { useText } from "../../core/utils/text";
import { Manifestation, Work } from "../../core/utils/types/entities";
import { WorkId } from "../../core/utils/types/ids";
import { useGetWork } from "../../core/utils/useGetWork";
import { useEditionSwitch } from "../../core/utils/useEditionSwitch";
import {
  divideManifestationsByMaterialType,
  getBestMaterialTypeForWork,
  getDetailsListData,
  getInfomediaIds,
  getManifestationChildrenOrAdults,
  getManifestationsOrderByTypeAndYear,
  isParallelReservation,
  getDisclosureOpenStatesFromUrl
} from "./helper";
import {
  ListItemType,
  ListData
} from "../../components/material/MaterialDetailsList";
import MaterialDisclosure from "./MaterialDisclosure";
import ReservationFindOnShelfModals from "./ReservationFindOnShelfModals";
import OnlineInternalModal from "../../components/reservation/OnlineInternalModal";
import MaterialGridRelated from "../../components/material-grid-related/MaterialGridRelated";
import useAvailabilityData from "../../components/availability-label/useAvailabilityData";
import { AccessTypeCodeEnum } from "../../core/dbc-gateway/generated/graphql";
import { useScrollToLocation } from "../../core/utils/UseScrollToLocation";
import EditionSwitchModal from "../../components/reservation/EditionSwitchModal";

export interface MaterialProps {
  wid: WorkId;
}

function stripPunctuationAndSpaces(string: string) {
  return (string || "").replace(/[^\w\s\']|_/g, "").replace(/\s+/g, " ");
}

// @ts-ignore-next-line
const systemAgency = JSON.parse(document.querySelector("[data-agency-config]")?.getAttribute("data-agency-config") || "{}")?.id || "";

function pointerToFilter(pointer: string) {
  if (pointer.includes("[") === false)
    return [{ pointer: pointer }];

  let filter = [];
  let chunks = pointer.split(/\[(.*?)\]/i);
  for (let i = 0; i < chunks.length; i += 2) {
    // is number only - then it's element index, a part of pointer.
    if (/^\d+$/.test(chunks[i + 1])) {
      chunks[i] = chunks[i] + "[" + chunks[i + 1] + "]"
      chunks[i + 1] = "";
    }

    let filterData: any = {
      pointer: lodash.trim(chunks[i], ".")
    };

    if (filterData.pointer === "")
      continue;

    if (chunks[i + 1]) {
      let filterOptions: any = {};
      chunks[i + 1].split(",").filter(Boolean).map(option => {
        let [property, modifier, value] = option.trim().split(/([\^\~]?)=/);
        // @ts-ignore-next-line
        value = value ? value.trim() : null;

        if (value === "$systemAgency") {
          value = systemAgency;
        }

        let trimmedProperty = property.trim();
        if (filterOptions[trimmedProperty]) {
          filterOptions[trimmedProperty] = lodash.castArray(filterOptions[trimmedProperty]);
          filterOptions[trimmedProperty].push({ value, modifier })
        } else {
          filterOptions[trimmedProperty] = { value, modifier };
        }
      });

      if (Object.keys(filterOptions).length !== 0) {
        filterData.filterOptions = filterOptions,
        filterData.filter = function(array: any) {
          return lodash.castArray(array).filter((value: any) => {
            return value != null && Object.keys(filterOptions).every(key => {
              let targetData = filterOptions[key];

              return lodash.castArray(targetData).every(_targetData => {
                if (_targetData.value == null)
                  return key in value;

                switch(_targetData.modifier) {
                  case "":
                    return value[key] == _targetData.value;
                  case "^":
                    return value[key].startsWith(_targetData.value);
                  case "~":
                    return value[key].includes(_targetData.value);
                }
              });
            });
          });
        };
      }
    }

    filter.push(filterData);
  }

  return filter;
}

// Support the lodash pointers + .propname[] - to traverse a property value as array.
function extendedGet(target: any, pointer: any): any {
  if (typeof pointer === "string")
    pointer = pointerToFilter(pointer);

  target = lodash.castArray(target);
  pointer.forEach((getterOptions: any) => {
    target = lodash.flatten(target.map((_target: any) => lodash.get(_target, getterOptions.pointer)));

    if (getterOptions.filter)
      target = getterOptions.filter(target);
  });

  return target;
}

function extendedFieldsDataGetter(pointers: string[], materialData: any, options: any) {
  options = options || {};
  pointers = lodash.castArray(pointers).filter(Boolean);

  if (pointers.length === 0)
    return [];

  if (pointers[0].startsWith("function")) {
    let customHandler = eval("(" + pointers.join("\n") + ")");

    return customHandler(extendedGet.bind(null, materialData));
  }


  let foundData: any = {
    data: [],
    filterBy: []
  };

  [{
    storage: "data",
    pointers: pointers
  }, {
    storage: "filterBy",
    pointers: options.filterBy
  }].filter((datum: any) => {
    return (datum.pointers || []).filter(Boolean).length > 0;
  }).forEach((datum: any) => {

    datum.pointers.forEach((pointer: string) => {
      pointer.split("||").some((orPointer: string) => {
        orPointer = orPointer.trim();

        let type: string = "graphql";
        if (orPointer.includes(":"))
          [type, orPointer] = orPointer.split(":");

        let fieldData: string|string[] = "";
        if (type === "marc") {
          fieldData = lodash.get(materialData?.parsedMarc, orPointer);
        } else if (type === "extraMarc") {
          fieldData = lodash.get(materialData?.parsedExtraMarc, orPointer);
        } else if (type === "graphql") {
          fieldData = extendedGet(materialData, orPointer);
        } else if (type.startsWith("manifestationMarc")) {
          let agency = type.replace("manifestationMarc", "");
          agency = agency.substring(1, agency.length - 1);

          if (agency === "systemAgency") {
            if (!systemAgency)
              return console.warn("System agency is not defined, by required by extended field!", `Type: "${ type }", pointer: "${ orPointer }".`);

            agency = systemAgency;
          }

          let targetManifestation: any;
          materialData.manifestations.all.concat([
            materialData.manifestations.bestRepresentation,
            materialData.manifestations.latest
          ]).some((manifestation: any) => {
            if (manifestation.pid.startsWith(agency)) {
              targetManifestation = manifestation;
            }

            return targetManifestation;
          });

          if (targetManifestation) {
            fieldData = lodash.get(targetManifestation?.parsedMarc, orPointer);
          }
        } else {
          console.warn(`Unknown getter type: "${ type }, pointer: "${ orPointer }"`);
        }

        if (Array.isArray(fieldData))
          fieldData = fieldData.filter((found: string) => found != null);

        if (!fieldData || fieldData.length === 0)
          return;

        if (Array.isArray(fieldData)) {
          foundData[datum.storage] = foundData[datum.storage].concat(fieldData);
        } else {
          foundData[datum.storage].push(fieldData);
        }

        return true;
      });
    });
  });

  ["data", "filterBy"].forEach((storage: string) => {
    foundData[storage] = foundData[storage].filter((dataChunk: any) => dataChunk && ("" + dataChunk).trim() !== "");
  });

  if (foundData.filterBy.length !== 0) {
    foundData.filterBy =  foundData.filterBy.map((dataChunk: string) => {
      return stripPunctuationAndSpaces(dataChunk);
    });

    foundData.data = foundData.data.filter((dataChunk: string) => {
      return foundData.filterBy.includes(stripPunctuationAndSpaces(dataChunk)) === false;
    });
  }

  return lodash.uniq(foundData.data);
}

function extendedFieldsDataMerge(filedData: any, originalData: any, customData: any, options: any) {
  if (!Array.isArray(originalData))
    originalData = [originalData];

  originalData = originalData.filter(Boolean);

  let mergedData = [];
  switch (filedData.insert) {
    case "prepend":
      mergedData = customData.concat(originalData);
    break;
    case "replace":
      mergedData = customData;
    break;
    case "fallback":
      if (originalData.length === 0) {
        mergedData = customData;
      } else {
        mergedData = originalData;
      }
    break;
    default: // append
      mergedData = originalData.concat(customData);
    break;
  }

  mergedData = mergedData.filter((datum: any) => datum && ("" + datum).trim());

  let outputType = filedData.type || options?.outputType || "text";
  if (outputType === "list") {
    return mergedData;
  }

  return mergedData.join(", ");
}

function hasExtraMarc(pointers: string[]) {
  return (pointers || []).some((pointer: string) => pointer.startsWith("extraMarc:"));
}

const Material: React.FC<MaterialProps> = ({ wid }) => {
  const t = useText();
  const [selectedManifestations, setSelectedManifestations] = useState<
    Manifestation[] | null
  >(null);
  const [selectedPeriodical, setSelectedPeriodical] =
    useState<PeriodicalEdition | null>(null);
  const { data: userData } = usePatronData();
  const [isUserBlocked, setIsUserBlocked] = useState<boolean | null>(null);
  const { updatePageStatistics } = usePageStatistics();
  const { collectPageStatistics } = useCollectPageStatistics();
  const disclosureOpenStates = getDisclosureOpenStatesFromUrl();
  const { handleReserveFirstAvailable } = useEditionSwitch(
    selectedManifestations
  );

  const customFields = useMemo(() => {
    // @ts-ignore-next-line
    let extendedFields: any = document.querySelector('[data-eonext-ext-fields]')?.dataset?.eonextExtFields;
    if (!extendedFields)
      return;

    try {
      extendedFields = JSON.parse(extendedFields);
      extendedFields.aliases = extendedFields.aliases || {};
      extendedFields._withExtraMarc = false;

      if (extendedFields.shelfmarkOverride)
        extendedFields.shelfmarkOverride.getter = extendedFieldsDataGetter.bind(null, extendedFields.shelfmarkOverride.data);

      Object.keys(extendedFields).forEach(sectionName => {
        if (["description", "detail"].includes(sectionName)) {
          Object.keys(extendedFields[sectionName]).forEach(fieldLabel => {
            extendedFields[sectionName][fieldLabel].label = fieldLabel;
            extendedFields[sectionName][fieldLabel].merge = extendedFieldsDataMerge.bind(null, extendedFields[sectionName][fieldLabel]);
            let pointers = extendedFields[sectionName][fieldLabel]?.data;
            if (!pointers && Array.isArray(extendedFields[sectionName][fieldLabel]))
              pointers = extendedFields[sectionName][fieldLabel];

            extendedFields[sectionName][fieldLabel].getter = extendedFieldsDataGetter.bind(null, pointers);
            if (hasExtraMarc(extendedFields[sectionName][fieldLabel]?.data)) {
              extendedFields._withExtraMarc = true;
            }

            extendedFields[sectionName][fieldLabel].findLabelIndex = (targetList: string[]) => {
              return targetList.findIndex((targetLabel: string) => {
                // Matched original
                if (targetLabel === fieldLabel)
                  return true;

                // Matched alias
                return (extendedFields.aliases[fieldLabel] || []).some((alias: string) => {
                  return targetLabel === alias;
                });
              });
            };

            extendedFields[sectionName][fieldLabel].findLabel = (targetList: string[]) => {
              const index = extendedFields[sectionName][fieldLabel].findLabelIndex(targetList);
              if (index === -1)
                return null;

              return targetList[index];
            };
          });
        } else if (sectionName === "additionalDescription") {
          extendedFields[sectionName].merge = extendedFieldsDataMerge.bind(null, extendedFields[sectionName]);
          extendedFields[sectionName].getter = (work: any) => {
            return extendedFieldsDataGetter(extendedFields[sectionName]?.body, work, {
              filterBy: extendedFields[sectionName]?.filterBodyBy
            });
          };

          if (hasExtraMarc(extendedFields[sectionName]?.body)) {
            extendedFields._withExtraMarc = true;
          }

          (extendedFields[sectionName].tags || []).forEach((tag: any) => {
            tag.merge = extendedFieldsDataMerge.bind(null, tag);
            tag.getter = (work: any) => {
              return extendedFieldsDataGetter(tag?.data, work, {
                filterBy: tag?.filterBy
              });
            };

            if (hasExtraMarc(tag?.data)) {
              extendedFields._withExtraMarc = true;
            }
          });
        }
      });

      return extendedFields;
    } catch (error) {
      console.warn("Cannot parse data-eonext-ext-fields attribute! Data: ", extendedFields, error);
    }

    return {};
  }, [wid]);

  const { data, isLoading, workType } = useGetWork(wid, customFields?._withExtraMarc, customFields?.shelfmarkOverride);

  useUpdateEffect(() => {
    updatePageStatistics({ waitTime: 2500 });
  }, [selectedManifestations, selectedPeriodical]);

  useEffect(() => {
    setIsUserBlocked(!!(userData?.patron && isBlocked(userData.patron)));
  }, [userData]);

  useDeepCompareEffect(() => {
    if (data?.work?.genreAndForm) {
      collectPageStatistics({
        ...statistics.materialGenre,
        trackedData: data.work.genreAndForm.join(", ")
      });
    }
    if (data?.work?.mainLanguages) {
      collectPageStatistics({
        ...statistics.materialLanguage,
        trackedData: data.work.mainLanguages
          .map((language) => language.display)
          .join(", ")
      });
    }
    if (data?.work?.dk5MainEntry) {
      collectPageStatistics({
        ...statistics.materialTopicNumber,
        trackedData: data.work.dk5MainEntry.display
      });
    }
    if (data?.work?.manifestations.bestRepresentation) {
      collectPageStatistics({
        ...statistics.materialAudience,
        trackedData: getManifestationChildrenOrAdults(
          data.work.manifestations.bestRepresentation as Manifestation
        )
      });
    }
    if (data?.work?.fictionNonfiction) {
      collectPageStatistics({
        ...statistics.materialFictionNonFiction,
        trackedData: data.work.fictionNonfiction.display
      });
    }
    // In this case we only want to track once - on work data load
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  useEffect(() => {
    if (!data?.work) return;
    const { work } = data as { work: Work };

    const urlType = getUrlQueryParam("type");
    const manifestationsByMaterialType = divideManifestationsByMaterialType(
      work.manifestations.all
    );

    let _selectedManifestation;
    let _bestMaterialType;

    if (urlType && manifestationsByMaterialType[urlType]?.length > 0) {
      // Use the type from the URL if it's present in the manifestations
      _selectedManifestation = manifestationsByMaterialType[urlType];
    }

    if (!_selectedManifestation) {
      // Otherwise, fallback to the best material type for the work
      const bestMaterialType = getBestMaterialTypeForWork(work);
      _selectedManifestation = manifestationsByMaterialType[bestMaterialType];
      _bestMaterialType = bestMaterialType;
    }

    if (!_selectedManifestation) {
      _selectedManifestation = [work?.manifestations?.bestRepresentation];
    }

    setSelectedManifestations(_selectedManifestation);
    if (_bestMaterialType) {
      setQueryParametersInUrl({ type: _bestMaterialType });
    }
  }, [data]);

  // We need availability in order to show availability text under action buttons
  const { isAvailable, isLoading: isAvailabilityLoading } = useAvailabilityData(
    {
      accessTypes: [AccessTypeCodeEnum.Physical, AccessTypeCodeEnum.Online],
      access: [undefined],
      faustIds: selectedManifestations
        ? getAllFaustIds(selectedManifestations)
        : [],
      isbn: null, // Not needed.
      // "manifestText" is used inside the availability hook to check whether the material is an article
      // which we check inside shouldShowMaterialAvailabilityText() helper here.
      manifestText: "NOT AN ARTICLE",
      enabled: !!selectedManifestations
    }
  );

  useScrollToLocation([data?.work, isAvailabilityLoading]);

  if (isLoading || !data?.work || !selectedManifestations) {
    return <MaterialSkeleton />;
  }

  const {
    work,
    work: {
      manifestations: { all: manifestations },
      relations: { hasReview }
    }
  } = data as { work: Work };

  const pid = getWorkPid(work);
  let detailsListData = getDetailsListData({
    manifestation: selectedManifestations[0],
    work,
    t
  });

  Object.values(customFields?.detail || {}).forEach((customField: any) => {
    let originalListElementLabels = detailsListData.map((originalListElement: any) => originalListElement?.label);
    let dataIndex = customField.findLabelIndex(originalListElementLabels);
    let customFieldValue = customField.getter(work);

    if (customField.url) {
      customFieldValue = lodash.castArray(customFieldValue).filter(Boolean).map((value: string) => {
        return {
          key: value,
          node: <a
            className="link-tag"
            href={ new URL((customField.url || "#").replace(/\$\{\s*tag\s*\}/ig, value), window.location.href).toString() }
          >
            { value }
          </a>
        };
      });
    }

    if (dataIndex === -1) {
      dataIndex = detailsListData.push({
        label: customField.label,
        value: customFieldValue
      }) - 1;
    } else {
      detailsListData[dataIndex].value = customField.merge(detailsListData[dataIndex].value, customFieldValue);
    }

    detailsListData[dataIndex].type = customField.type || "standard";

    if (detailsListData[dataIndex].value.length === 0 || customField.hidden === true)
      detailsListData.splice(dataIndex, 1);
  });

  const infomediaIds = getInfomediaIds(selectedManifestations);

  return (
    <>
      <section className="material-page">
        <MaterialHeader
          wid={wid}
          work={work}
          selectedManifestations={selectedManifestations}
          setSelectedManifestations={setSelectedManifestations}
          selectedPeriodical={selectedPeriodical}
          selectPeriodicalHandler={setSelectedPeriodical}
          isGlobalMaterial={workType === "global"}
          isAvailable={isAvailable}
        >
          {manifestations.map((manifestation) =>
            hasCorrectAccessType(AccessTypeCodeEnum.Online, [manifestation]) ? (
              <OnlineInternalModal
                key={manifestation.pid}
                workId={wid}
                selectedManifestations={[manifestation]}
              />
            ) : (
              <ReservationFindOnShelfModals
                key={manifestation.pid}
                patron={userData?.patron}
                manifestations={[manifestation]}
                selectedPeriodical={selectedPeriodical}
                work={work}
                setSelectedPeriodical={setSelectedPeriodical}
              />
            )
          )}
          {infomediaIds.length > 0 && !isAnonymous() && !isUserBlocked && (
            <InfomediaModal
              selectedManifestations={selectedManifestations}
              infoMediaId={infomediaIds[0]}
            />
          )}
          {hasCorrectAccess("DigitalArticleService", selectedManifestations) &&
            !isAnonymous() &&
            !isUserBlocked && (
              <DigitalModal pid={selectedManifestations[0].pid} workId={wid} />
            )}
          {/* Only create a main version of "reservation" & "find on shelf" modal for physical materials with multiple editions.
        Online materials lead to external links, or to same modals as are created for singular editions. */}
          {isParallelReservation(selectedManifestations) && (
            <ReservationFindOnShelfModals
              patron={userData?.patron}
              manifestations={selectedManifestations}
              selectedPeriodical={selectedPeriodical}
              work={work}
              setSelectedPeriodical={setSelectedPeriodical}
            />
          )}
          <EditionSwitchModal
            work={work}
            workId={wid}
            handleReserveFirstAvailable={handleReserveFirstAvailable}
          />
        </MaterialHeader>
        <div className="material-description-group">
          <MaterialAdditionalDescription work={work} fieldsOptions={ customFields?.additionalDescription } />
          <MaterialDescription pid={pid} work={work} customFields={ customFields?.description } />
        </div>
        <div className="disclosure-section">
          {/* Since we cannot trust the editions for global manifestations */}
          {/* we limit them to only occur if the loaded work is global */}
          {workType === "local" && (
            <MaterialDisclosure
              title={`${t("editionsText")} (${manifestations.length})`}
              icon={VariousIcon}
              dataCy="material-editions-disclosure"
              open={disclosureOpenStates.editions}
            >
              <>
                {getManifestationsOrderByTypeAndYear(manifestations).map(
                  (manifestation: Manifestation) => {
                    return (
                      <MaterialMainfestationItem
                        key={manifestation.pid}
                        manifestation={manifestation}
                        workId={wid}
                      />
                    );
                  }
                )}
              </>
            </MaterialDisclosure>
          )}
          <MaterialDisclosure
            dataCy="material-details-disclosure"
            title={t("detailsText")}
            icon={Receipt}
            open={disclosureOpenStates.details}
          >
            <MaterialDetailsList
              id={`material-details-${wid}`}
              className="pl-80 pb-48"
              data={detailsListData}
            />
          </MaterialDisclosure>
          {hasReview && hasReview.length > 0 && (
            <MaterialDisclosure
              dataCy="material-reviews-disclosure"
              title={t("reviewsText")}
              icon={CreateIcon}
            >
              <MaterialReviews pids={hasReview.map((review) => review.pid)} />
            </MaterialDisclosure>
          )}
        </div>
      </section>
      {work && (
        <section>
          <MaterialGridRelated work={work} />
        </section>
      )}
    </>
  );
};

export default Material;
