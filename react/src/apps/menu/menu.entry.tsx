import React, { FC } from "react";
import Menu from "./menu";
import { withText } from "../../core/utils/text";
import { withConfig } from "../../core/utils/config";
import { withUrls } from "../../core/utils/url";
import GlobalUrlEntryPropsInterface from "../../core/utils/types/global-url-props";
import { GroupModalProps } from "../../core/storybook/groupModalArgs";
import { GroupModalLoansProps } from "../../core/storybook/loanGroupModalArgs";
import { GroupModalReservationsProps } from "../../core/storybook/reservationGroupModalArgs";
import { pageSizeGlobal } from "../../core/utils/helpers/general";
import { MaterialDetailsModalProps } from "../../core/storybook/materialDetailsModalArgs";
import { ReservationMaterialDetailsProps } from "../../core/storybook/reservationMaterialDetailsArgs";
import { DeleteReservationModalArgs } from "../../core/storybook/deleteReservationModalArgs";
import { RenewalArgs } from "../../core/storybook/renewalArgs";
import { GlobalEntryTextProps } from "../../core/storybook/globalTextArgs";

export interface MenuProps {
  menuUserProfileUrlText: string;
  userProfileUrl: string;
  menuNavigationDataConfig: string;
  menuNotificationLoansExpiredText: string;
  menuNotificationLoansExpiredUrl: string;
  readyForLoanText: string;
  menuNotificationLoansExpiringSoonText: string;
  menuNotificationLoansExpiringSoonUrl: string;
  menuNotificationReadyForPickupText: string;
  menuNotificationReadyForPickupUrl: string;
  menuLogOutText: string;
  loansSoonOverdueText: string;
  loansOverdueText: string;
  logoutUrl: string;
  expirationWarningDaysBeforeConfig: string;
  feeListDaysText: string;
  menuLoginText: string;
  menuLoginUrl: string;
  menuSignUpText: string;
  reservationsReadyText: string;
  menuSignUpUrl: string;
  menuProfileLinksAriaLabelText: string;
  menuUserIconAriaLabelText: string;
  menuUserIconAriaLabelLoggedOutText: string;
  menuNotAuthenticatedCloseButtonText: string;
  menuAuthenticatedCloseButtonText: string;
  menuAuthenticatedModalDescriptionText: string;
  menuNotAuthenticatedModalDescriptionText: string;
  physicalLoansUrl: string;
  reservationsUrl: string;
  searchHeaderLoginText: string;
  searchHeaderFavoritesText: string;
  materialAndAuthorText: string;
  materialByAuthorText: string;
  statusBadgeWarningText: string;
  isInjectionExample?: boolean;
}

export interface MenuEntryProps
  extends
    MenuProps,
    GlobalUrlEntryPropsInterface,
    GroupModalProps,
    GroupModalLoansProps,
    RenewalArgs,
    DeleteReservationModalArgs,
    GroupModalReservationsProps,
    MaterialDetailsModalProps,
    GlobalEntryTextProps,
    ReservationMaterialDetailsProps {}

const MenuEntry: FC<MenuEntryProps> = ({ pageSizeDesktop, pageSizeMobile, isInjectionExample }) => {
  const pageSize = pageSizeGlobal(
    {
      desktop: pageSizeDesktop,
      mobile: pageSizeMobile
    },
    "pageSizeLoanList"
  );

  // Required to force React re-render when injection is mounted. Only for dev!
  const [currentDate, setCurrentDate] = React.useState(0);

  if (isInjectionExample) {
    React.useEffect(() => {
      // @ts-ignore-next-line
      if (window.InleadReactInjector) {
        // @ts-ignore-next-line
        window.InleadReactInjector.clearInjections();
      }

      // @ts-ignore-next-line
      import("./GoogleTranslationsInjection.jsx").then(() => {
        setCurrentDate(Date.now());
      });
    }, []);

    return <div data-current-date={ currentDate || "" }>
      <Menu pageSize={pageSize} />
    </div>;
  }

  return <Menu pageSize={pageSize} />;
};

export default withUrls(withConfig(withText(MenuEntry)));
