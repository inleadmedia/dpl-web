import React, { FC, useState, useEffect } from "react";
import { withText } from "../../core/utils/text";
import { withUrls } from "../../core/utils/url";
import OpeningHoursSidebar, {
  OpeningHoursSidebarType
} from "./OpeningHoursSidebar";
import { withConfig } from "../../core/utils/config";

interface OpeningHoursClockEntryTextProps {
  openingHoursText: string;
  openingHoursSidebarTitleText: string;
  openingHoursSidebarLinkText: string;
}

interface OpeningHoursClockEntryConfigProps {
  openingHoursSidebarBranchesConfig: string;
}

const OpeningHoursSidebarEntry: FC<
  OpeningHoursClockEntryTextProps &
    OpeningHoursSidebarType &
    OpeningHoursClockEntryConfigProps
> = ({ size, isInjectionExample }) => {
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
      import("./ExpandedOpeningHoursSidebarInjection.jsx").then(() => {
        setCurrentDate(Date.now());
      });
    }, []);
  }

  return <div data-current-date={ currentDate || "" }>
    <OpeningHoursSidebar size={size} />
  </div>;
};

export default withConfig(withUrls(withText(OpeningHoursSidebarEntry)));
