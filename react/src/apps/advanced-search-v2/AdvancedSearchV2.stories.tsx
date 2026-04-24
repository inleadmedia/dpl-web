import type { Meta, StoryObj } from "@storybook/react";
import serviceUrlArgs, {
  argTypes as serviceUrlArgTypes
} from "../../core/storybook/serviceUrlArgs";
import AdvancedSearchV2Entry from "./AdvancedSearchV2.entry";
import globalTextArgs, {
  argTypes as globalTextArgTypes
} from "../../core/storybook/globalTextArgs";
import globalConfigArgs, {
  argTypes as globalConfigArgTypes
} from "../../core/storybook/globalConfigArgs";
import mappArgs, {
  argTypes as mappArgTypes
} from "../../core/storybook/mappArgs";
import advancedSearchV2Args, {
  argTypes as advancedSearchV2ArgTypes
} from "../../core/storybook/advancedSearchV2Args";
import advancedSearchV2SortArgs, {
  argTypes as advancedSearchV2SortArgTypes
} from "../../core/storybook/advancedSearchV2SortArgs";
import copyLinkArgs, {
  argTypes as copyLinkArgsTypes
} from "../../core/storybook/copyLinkArgs";
import advancedSearchV2CqlSearchArgs, {
  argTypes as advancedSearchV2CqlSearchArgTypes
} from "../../core/storybook/advancedSearchV2CqlSearchArgs";

const meta: Meta<typeof AdvancedSearchV2Entry> = {
  title: "Apps / Advanced Search V2",
  component: AdvancedSearchV2Entry,
  // eslint-disable-next-line @typescript-eslint/ban-ts-comment
  // @ts-ignore: can't figure out how to type serviceUrlArgTypes and globalTextArgTypes
  argTypes: {
    ...serviceUrlArgTypes,
    ...globalTextArgTypes,
    ...globalConfigArgTypes,
    ...mappArgTypes,
    ...advancedSearchV2ArgTypes,
    ...advancedSearchV2SortArgTypes,
    ...copyLinkArgsTypes,
    ...advancedSearchV2CqlSearchArgTypes,
    pageSizeDesktop: {
      description: "Number of search result items on desktop",
      control: { type: "number" },
      table: {
        type: { summary: "number" },
        defaultValue: { summary: "50" }
      }
    },
    pageSizeMobile: {
      description: "Number of search result items on mobile",
      control: { type: "number" },
      table: {
        type: { summary: "number" },
        defaultValue: { summary: "20" }
      }
    },
    addMoreFiltersText: {
      description: "Add more filters text",
      control: { type: "text" }
    },
    materialUrl: {
      description: "Path to the material page",
      control: { type: "text" }
    },
    authUrl: {
      description: "Url where user can authenticate",
      control: { type: "text" }
    },
    searchUrl: {
      description: "Path to the search result page",
      control: { type: "text" }
    },
    byAuthorText: {
      description: "By (author) Text",
      control: { type: "text" }
    },
    inSeriesText: {
      description: "In series",
      control: { type: "text" }
    },
    showMoreText: {
      description: "Show more Text",
      control: { type: "text" }
    },
    resultPagerStatusText: {
      description: "Result pager status text",
      control: { type: "text" }
    },
    searchShowingMaterialsText: {
      description: "Showing materials",
      control: { type: "text" }
    },
    blacklistedPickupBranchesConfig: {
      description: "Blacklisted Pickup branches",
      control: { type: "text" }
    },
    blacklistedAvailabilityBranchesConfig: {
      description: "Blacklisted Availability branches",
      control: { type: "text" }
    },
    blacklistedSearchBranchesConfig: {
      description: "Blacklisted branches",
      control: { type: "text" }
    },
    branchesConfig: {
      description: "Branches",
      control: { type: "text" }
    },
    loadingText: {
      description: "Loading",
      control: { type: "text" }
    },
    loadingResultsText: {
      description: "Advanced search loading results text",
      control: { type: "text" }
    }
  }
};

export default meta;

type Story = StoryObj<typeof AdvancedSearchV2Entry>;

export const Default: Story = {
  args: {
    ...serviceUrlArgs,
    ...globalTextArgs,
    ...globalConfigArgs,
    ...mappArgs,
    ...advancedSearchV2Args,
    ...advancedSearchV2SortArgs,
    ...copyLinkArgs,
    ...advancedSearchV2CqlSearchArgs,
    etAlText: "et al.",
    pageSizeDesktop: 50,
    pageSizeMobile: 20,
    materialUrl: "/work/:workid",
    authUrl: "",
    searchUrl: "/search",
    byAuthorText: "By",
    inSeriesText: "in series",
    showMoreText: "show more",
    resultPagerStatusText: "Showing @itemsShown out of @hitcount results",
    searchShowingMaterialsText: "@hitcount materials",
    blacklistedPickupBranchesConfig:
      "FBS-751032,FBS-751031,FBS-751009,FBS-751027,FBS-751024",
    blacklistedAvailabilityBranchesConfig:
      "FBS-751032,FBS-751031,FBS-751009,FBS-751027,FBS-751024",
    blacklistedSearchBranchesConfig:
      "FBS-751032,FBS-751031,FBS-751009,FBS-751027,FBS-751024",
    branchesConfig:
      '[{"branchId":"DK-830630","title":"Aalborg"},{"branchId":"DK-830480","title":"Aarhus"}]',
    loadingText: "Loading",
    loadingResultsText: "Loading results...",
    addMoreFiltersText: "More filters"
  }
};
