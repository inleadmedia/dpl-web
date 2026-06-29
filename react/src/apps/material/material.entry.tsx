import * as React from "react";
import GuardedApp from "../../components/guarded-app";
import { withConfig } from "../../core/utils/config";
import { withText } from "../../core/utils/text";
import { WorkId } from "../../core/utils/types/ids";
import { withUrls } from "../../core/utils/url";
import Material from "./material";
import GlobalUrlEntryPropsInterface from "../../core/utils/types/global-url-props";
import { GlobalEntryTextProps } from "../../core/storybook/globalTextArgs";
import { DeleteReservationModalArgs } from "../../core/storybook/deleteReservationModalArgs";
import { PublizonErrorArgs } from "../../core/storybook/publizonErrorArgs";
import { CopyLinkArgs } from "../../core/storybook/copyLinkArgs";
import { MappArgs } from "../../core/storybook/mappArgs";
import { EditionSwitchModalArgs } from "../../core/storybook/editionSwitchModalArgs";
import { PlayerModalProps } from "../../core/storybook/playerModalArgs";
import withPageStatistics from "../../core/statistics/withPageStatistics";
import useSetSmoothScroll from "../../core/utils/useSetSmoothScroll";

interface MaterialEntryTextProps {
  alreadyReservedText: string;
  approveLoanText: string;
  approveReservationText: string;
  blockedButtonText: string;
  cantReserveText: string;
  cantViewReviewText: string;
  cantViewText: string;
  changeEmailText: string;
  changeSmsNumberText: string;
  chooseOneText: string;
  closeText: string;
  daysText: string;
  descriptionHeadlineText: string;
  detailsListAudienceText: string;
  detailsListAgeRangeText: string;
  detailsListAuthorsText: string;
  detailsListNotesText: string;
  detailsListPhysicalDescriptionText: string;
  detailsListHostPublicationText: string;
  detailsListSourceText: string;
  detailsListPartsText: string;
  detailsListContributorsText: string;
  detailsListEditionText: string;
  detailsListFirstEditionYearText: string;
  detailsListFirstEditionYearUnknownText: string;
  detailsListGenreAndFormText: string;
  detailsListIsbnText: string;
  detailsListLanguageText: string;
  detailsListOriginalTitleText: string;
  detailsListPlayTimeText: string;
  detailsListPublisherText: string;
  detailsListScopeText: string;
  detailsListTypeText: string;
  detailsOfTheMaterialText: string;
  detailsText: string;
  editionsText: string;
  editionText: string;
  etAlText: string;
  expandMoreText: string;
  fictionNonfictionText: string;
  filmAdaptationsText: string;
  findOnBookshelfText: string;
  findOnShelfModalCloseModalAriaLabelText: string;
  findOnShelfModalListFindOnShelfText: string;
  findOnShelfModalListItemCountText: string;
  findOnShelfModalListMaterialText: string;
  findOnShelfModalNoLocationSpecifiedText: string;
  findOnShelfModalPeriodicalEditionDropdownText: string;
  findOnShelfModalPeriodicalYearDropdownText: string;
  findOnShelfModalScreenReaderModalDescriptionText: string;
  findOnShelfTableDescriptionText: string;
  firstAvailableEditionText: string;
  getOnlineText: string;
  goToText: string;
  reservationDetailsNoInterestAfterTitleText: string;
  identifierText: string;
  infomediaCopyrightText: string;
  infomediaModalCloseModalAriaLabelText: string;
  infomediaModalScreenReaderModalDescriptionText: string;
  inSameSeriesText: string;
  inSeriesText: string;
  instantLoanSubTitleText: string;
  instantLoanTitleText: string;
  instantLoanUnderlineDescriptionText: string;
  interestPeriodsConfig: string;
  libraryAssessmentText: string;
  librariesHaveTheMaterialText: string;
  listenOnlineText: string;
  loadingText: string;
  loanWithMaterialTypeText: string;
  loginToSeeReviewText: string;
  materialHeaderAllEditionsText: string;
  materialHeaderAuthorByText: string;
  materialGridRelatedTitleText: string;
  materialGridRelatedRecommendationsDataLabelText: string;
  materialGridRelatedSeriesDataLabelText: string;
  materialGridRelatedAuthorDataLabelText: string;
  materialGridRelatedSelectAriaLabelText: string;
  materialGridRelatedInlineFiltersAriaLabelText: string;
  materialIsAvailableInAnotherEditionText: string;
  materialIsIncludedText: string;
  materialIsLoanedOutText: string;
  materialReservationInfoText: string;
  materialsInStockInfoText: string;
  missingDataText: string;
  modalReservationFormEmailHeaderDescriptionText: string;
  modalReservationFormEmailHeaderTitleText: string;
  modalReservationFormEmailInputFieldDescriptionText: string;
  modalReservationFormEmailInputFieldLabelText: string;
  modalReservationFormPickupHeaderDescriptionText: string;
  modalReservationFormPickupHeaderTitleText: string;
  modalReservationFormSmsHeaderDescriptionText: string;
  modalReservationFormSmsHeaderTitleText: string;
  modalReservationFormSmsInputFieldDescriptionText: string;
  modalReservationFormSmsInputFieldLabelText: string;
  notLivingInMunicipalityText: string;
  numberDescriptionText: string;
  numberInQueueText: string;
  okButtonText: string;
  onlineInternalModalCloseAriaLabelText: string;
  onlineInternalModalScreenReaderDescriptionText: string;
  onlineInternalModalEnsureNotificationText: string;
  onlineInternalResponseErrorSubtitleText: string;
  onlineInternalResponseErrorTitleText: string;
  onlineInternalResponseLoanedSubtitleText: string;
  onlineInternalResponseLoanedTitleText: string;
  onlineInternalResponseReservedSubtitleText: string;
  onlineInternalResponseReservedTitleText: string;
  onlineInternalErrorsText: string;
  onlineInternalSuccessLoanedText: string;
  onlineInternalSuccessManualBorrowingNoticeText: string;
  onlineInternalSuccessReservedText: string;
  onlineLimitMonthAudiobookInfoText: string;
  onlineLimitMonthEbookInfoText: string;
  onlineMaterialPlayerText: string;
  onlineMaterialReaderText: string;
  onlineMaterialTeaserText: string;
  openOrderAuthenticationErrorText: string;
  openOrderErrorMissingPincodeText: string;
  openOrderInvalidOrderText: string;
  openOrderNoServicerequesterText: string;
  openOrderNotOwnedIllLocText: string;
  openOrderNotOwnedNoIllLocText: string;
  openOrderNotOwnedWrongIllMediumtypeText: string;
  openOrderOrsErrorText: string;
  openOrderOwnedOwnCatalogueText: string;
  openOrderOwnedWrongMediumtypeText: string;
  openOrderResponseTitleText: string;
  openOrderServiceUnavailableText: string;
  openOrderStatusOwnedAcceptedText: string;
  openOrderUnknownErrorText: string;
  openOrderUnknownPickupagencyText: string;
  openOrderUnknownUserText: string;
  openOrderUserBlockedByAgencyText: string;
  openOrderUserNoLongerExistOnAgencyText: string;
  openOrderUserNotVerifiedText: string;
  orderDigitalCopyButtonLoadingText: string;
  orderDigitalCopyButtonText: string;
  orderDigitalCopyDescriptionText: string;
  orderDigitalCopyEmailLabelText: string;
  orderDigitalCopyFeedbackButtonText: string;
  orderDigitalCopyFeedbackErrorAgencyNotSubscribedText: string;
  orderDigitalCopyFeedbackErrorInvalidPickupBranchText: string;
  orderDigitalCopyFeedbackErrorMissingClientConfigurationText: string;
  orderDigitalCopyFeedbackErrorPidNotReservableText: string;
  orderDigitalCopyFeedbackErrorUnauthenticatedUserText: string;
  orderDigitalCopyFeedbackOkText: string;
  orderDigitalCopyFeedbackTitleText: string;
  orderDigitalCopyFeedbackBorchkUserBlockedByAgencyText: string;
  orderDigitalCopyFeedbackBorchkUserNotVerifiedText: string;
  orderDigitalCopyFeedbackBorchkUserNoLongerExistOnAgencyText: string;
  orderDigitalCopyFeedbackErrorMunicipalityagencyidNotFoundText: string;
  orderDigitalCopyFeedbackUnknownUserText: string;
  orderDigitalCopyModalCloseModalAriaLabelText: string;
  orderDigitalCopyModalScreenReaderModalDescriptionText: string;
  orderDigitalCopyFeedbackErrorMissingMunicipalityagencyidText: string;
  orderDigitalCopyFeedbackInternalErrorText: string;
  orderDigitalCopyTitleText: string;
  outOfText: string;
  periodicalSelectEditionText: string;
  periodicalSelectYearText: string;
  queueText: string;
  ratingIsText: string;
  readArticleText: string;
  receiveEmailWhenMaterialReadyText: string;
  receiveSmsWhenMaterialReadyText: string;
  reservableFromAnotherLibraryMissingEmailText: string;
  reservableFromAnotherLibraryExtraInfoText: string;
  reservableFromAnotherLibraryText: string;
  reservationDetailsPickUpAtTitleText: string;
  reservationErrorsDescriptionText: string;
  reservationErrorsTitleText: string;
  reservationModalCloseModalAriaLabelText: string;
  reservationModalScreenReaderModalDescriptionText: string;
  reservationSuccesIsReservedForYouText: string;
  reservationSuccessPreferredPickupBranchText: string;
  reservationSuccesTitleText: string;
  reserveBookText: string;
  reserveText: string;
  reserveWithMaterialTypeText: string;
  relatedContentText: string;
  reviewsText: string;
  saveButtonText: string;
  seeOnlineText: string;
  shiftText: string;
  subjectNumberText: string;
  tryAginButtonText: string;
  shareOnFacebookText: string;
  shareOnFacebookAriaLabelText: string;
  copyLinkText: string;
  copyLinkAriaLabelText: string;
  copyLinkSuccessText: string;
}

interface MaterialEntryConfigProps {
  agencyIdConfig: string;
  blacklistedAvailabilityBranchesConfig?: string;
  blacklistedInstantLoanBranchesConfig: string;
  blacklistedPickupBranchesConfig?: string;
  branchesConfig: string;
  findOnShelfDisclosuresDefaultOpenConfig: string;
  findOnShelfHideUnavailableHoldingsConfig: string;
  instantLoanConfig: string;
  shareConfig?: string;
  localSubjectsAgencyIdsConfig?: string;
  smsNotificationsForReservationsEnabledConfig: string;
}

export interface MaterialEntryProps
  extends
    GlobalUrlEntryPropsInterface,
    MaterialEntryTextProps,
    GlobalEntryTextProps,
    MaterialEntryConfigProps,
    DeleteReservationModalArgs,
    PublizonErrorArgs,
    CopyLinkArgs,
    MappArgs,
    EditionSwitchModalArgs,
    PlayerModalProps {
  wid: WorkId;
}

/*
  Extended fields must be defined at the JSON format with following schema.
  Do not paste comments to the configuration, because it's not supported by JSON!

  You can define any custom field name or override an existing one. To override the field open the material page
  and copy it's name, then use copied name as section property. All custom fields will be added to the end of given section.

  {
    // Settings for the detail section of the material page
    "detail": {
      "Field name": {
        // Data for the custom field, can be taken from the different sources.
        // "marc" - Data from the parsed `work.marc.content` GraphQL response.
        // where number (e.g.: 001) it's datafield tag value `<datafield ind1='0' ind2='0' tag='001'>...</datafield >`
        // letter (e.g.: a) it's a code of the found datafield `<subfield code='a'>131285299</subfield>`
        // In some cases there might be provided multiple datafields with the same tag, in this case all found values
        // will be used for a custom field.
        //
        // "graphql" - any field from the `work` GraphQL response. In case the pointer will reffer to a list\array of
        // values then all it's values will be used for a custom field. But make sure that list is a list of strings or numbers,
        // in other case data serialization will return unexpected results, like "[object Object],[object Object]"
        "data": ["marc:001.a", "graphql:titles.full[0]"],

        // Optional
        // The way to insert the custom field, by default "append", required only for extend the existing fields.
        // Add string data will be joined with " ," delimiter.
        // Supports following options:
        // "append" - Add the field data to the end of existing field data.
        // "prepend" - Add the field data will be added before of the existing field data.
        // "replace" - New data will replace existing data, even if there no found data for a given rules (field will be hided)
        // "fallback" - The new data will be visible only in case the original data is empty.
        "insert": "prepend",

        // Optional
        // Boolean flag to hide an field when it's true.
        "hidden": true,

        // Detail section specific fields!
        //
        // Optional
        // Way to display the data, by default "text". Supports following values:
        // "text" - the data will be displayed as the single string splitted by " ," delimiter.
        // "list" - the text data will be splitted by " ," delimiter and then displayed as the ul>li elements.
        "type": "list"
      },
      "description": {
        "Emnetal": {
          // The same options as for the detail section above.
          "data": ["marc:001.c"]
          "insert": "replace",
          "hidden": false,

          // Description section specific fields!
          //
          // Optional.
          // The link of the tag, by default "#".
          // Any relative or absolute link that will be used for the tags. The `${tag}` from the url template will be
          // replaced to the tag value. E.g. `marc:001.c` will return two values: "2021" and "10210".
          // Then there will be displayed two following tags: `<a href="/search?q=2021">2021</a>`
          // and `<a href="/search?q=10210">10210</a>`.
          "url": "/search?q=${tag}"
        }
      }
    }
  }
*/

const WrappedMaterialEntry: React.FC<MaterialEntryProps> = ({ wid }) => {
  useSetSmoothScroll();
  return (
    <GuardedApp app="material">
      <Material wid={wid} />
    </GuardedApp>
  );
};

export default withConfig(
  withUrls(withText(withPageStatistics(WrappedMaterialEntry, 5000)))
);
