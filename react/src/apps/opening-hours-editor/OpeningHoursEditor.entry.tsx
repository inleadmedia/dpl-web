import React, { useState, useEffect } from "react";
import { withText } from "../../core/utils/text";
import { withUrls } from "../../core/utils/url";
import OpeningHoursEditor, {
  OpeningHoursEditorType
} from "./OpeningHoursEditor";
import { withConfig } from "../../core/utils/config";
import { getInitialDateFromUrl } from "./helper";

interface OpeningHoursEditorEntryTextProps {
  openingHoursLoadingText: string;
  openingHoursRemoveEventButtonText: string;
  openingHoursInvalidEventText: string;
  openingHoursEventFormCategoryText: string;
  openingHoursEventFormStartTimeText: string;
  openingHoursEventFormEndTimeText: string;
  openingHoursEventFormSubmitText: string;
  openingHoursEventFormRepeatedText: string;
  openingHoursEventFormEndDateText: string;
  openingHoursEventFormEveryWeekdayText: string;
  openingHoursEventFormStartDateText: string;
  openingHoursConfirmAddRepeatedText: string;
  openingHoursConfirmAddRepeatedCancelText: string;
  openingHoursConfirmRepeatedSubmitText: string;
  openingHoursRepeatedIconAltText: string;
  openingHoursEditEventConfirmOptionThisText: string;
  openingHoursEditEventConfirmOptionAllText: string;
  openingHoursRemoveEventTitleText: string;
  openingHoursEditEventTitleText: string;
}

interface OpeningHoursEditorEntryConfigProps {
  openingHoursEditorCategoriesConfig: string;
  openingHoursBranchIdConfig: string;
}

const OpeningHoursEditorEntry: React.FC<
  OpeningHoursEditorEntryTextProps &
    OpeningHoursEditorType &
    OpeningHoursEditorEntryConfigProps
> = ({ initialDate, isInjectionExample }) => {
  const initialDateParam = getInitialDateFromUrl();
  // Required to force React re-render when injection is mounted. Only for dev!
  const [currentDate, setCurrentDate] = useState(0);

  if (isInjectionExample) {
    useEffect(() => {
      // @ts-ignore-next-line
      if (window.InleadReactInjector) {
        // @ts-ignore-next-line
        window.InleadReactInjector.clearInjections();
      }

      // @ts-ignore-next-line
      import("./OpeningHoursEditorCustomLocaleInjection.jsx").then(() => {
        setCurrentDate(Date.now());
      });
    }, []);
  }

  return <div data-current-date={ currentDate || "" }>
    <OpeningHoursEditor
      initialDate={initialDate ?? (initialDateParam || new Date())}
    />
  </div>;
};

export default withConfig(withUrls(withText(OpeningHoursEditorEntry)));
