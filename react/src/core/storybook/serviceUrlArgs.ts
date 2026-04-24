import { serviceUrlKeys } from "../utils/reduxMiddleware/extractServiceBaseUrls";

export const argTypes = {
  [serviceUrlKeys.fbs]: {
    description: "Base url for the FBS API",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary: "https://fbs-openplatform.dbc.dk"
      }
    }
  },
  [serviceUrlKeys.publizon]: {
    description: "Base url for the Publizon API",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary: "https://pubhub-openplatform.dbc.dk"
      }
    }
  },
  [serviceUrlKeys.wayfinder]: {
    name: "Base url for the Wayfinder API (global inventory)",
    defaultValue: process.env.WAYFINDER_BASEURL,
    control: { type: "text" }
  },
  [serviceUrlKeys.dplCms]: {
    description: "Base url for the DPL CMS API",
    defaultValue: "https://dpl-cms.inlead.dev",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary: "https://dpl-cms.docker"
      }
    }
  },
  [serviceUrlKeys.cover]: {
    description: "Base url for the cover service",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary: process.env.COVERS_BASEURL ?? "https://cover.dandigbib.org"
      }
    }
  },
  [serviceUrlKeys.materialList]: {
    description: "Base url for the material list service",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary: "https://prod.materiallist.dandigbib.org"
      }
    }
  },
  [serviceUrlKeys.fbi]: {
    description: "Base url for the FBI API",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary:
          process.env.GRAPHQL_API_BASEURL ??
          "https://temp.fbi-api.dbc.dk/next-present/graphql"
      }
    }
  },
  [serviceUrlKeys.fbiLocal]: {
    description: "Base url for the FBI API (local inventory)",
    defaultValue: "https://fbi-api.dbc.dk/opac/graphql",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary:
          process.env.GRAPHQL_API_BASEURL ??
          "https://temp.fbi-api.dbc.dk/next/graphql"
      }
    }
  },
  [serviceUrlKeys.fbiGlobal]: {
    description: "Base url for the FBI API (global inventory)",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary:
          process.env.GRAPHQL_API_BASEURL ??
          "https://temp.fbi-api.dbc.dk/next-present/graphql"
      }
    }
  },
  userinfoUrl: {
    description: "Adgangsplatfomen userinfo url",
    control: { type: "text" },
    table: {
      type: { summary: "text" },
      defaultValue: {
        summary: "https://login.bib.dk/userinfo"
      }
    }
  }
};

const envOptions = {
  [serviceUrlKeys.fbs]:
    process.env.FBS_BASEURL ?? "https://fbs-openplatform.dbc.dk",
  [serviceUrlKeys.publizon]:
    process.env.PUBLIZON_BASEURL ?? "https://pubhub-openplatform.dbc.dk",
  [serviceUrlKeys.dplCms]: process.env.CMS_BASEURL ?? "https://dpl-cms.docker",
  [serviceUrlKeys.cover]:
    process.env.COVERS_BASEURL ?? "https://cover.dandigbib.org",
  [serviceUrlKeys.materialList]: "https://prod.materiallist.dandigbib.org",
  [serviceUrlKeys.fbi]: process.env.GRAPHQL_API_BASEURL ?? "https://temp.fbi-api.dbc.dk/next-present/graphql",
  [serviceUrlKeys.fbiLocal]: process.env.GRAPHQL_API_BASEURL ?? "https://temp.fbi-api.dbc.dk/next/graphql",
  [serviceUrlKeys.fbiGlobal]: process.env.GRAPHQL_API_BASEURL ?? "https://temp.fbi-api.dbc.dk/next-present/graphql",
  [serviceUrlKeys.wayfinder]: process.env.WAYFINDER_BASEURL,
  developmentOptions: process.env.USE_DEVELOPMENT_OPTIONS,
  userinfoUrl: "https://login.bib.dk/userinfo"
};

export default envOptions;

if (typeof window === "object" && process.env.USE_DEVELOPMENT_OPTIONS === "true") {
  const extendedCovers = "cover.detail";
  const extendedFields = {
    shelfmarkOverride: {
      data: "652.m[0]"
    },
    additionalDescription: {
      label: "Additional description",
      body: ["manifestationMarc(systemAgency):504.byLang.kal"],
      //filterBodyBy: ["graphql:abstract"],
      //filterBodyBy: [{ data: "graphql:abstract", similarity: 0.8 }],
      tags: [{
        label: "Custom tags",
        data: [
          "manifestationMarc(systemAgency):667.f",
          "manifestationMarc(systemAgency):667.t",
          "manifestationMarc(systemAgency):667.e",
          "manifestationMarc(systemAgency):667.s",
          "manifestationMarc(systemAgency):667.r",
          "manifestationMarc(systemAgency):667.q",
          "manifestationMarc(systemAgency):667.m",
          "manifestationMarc(systemAgency):667.n",
          "manifestationMarc(systemAgency):667.p",
          "manifestationMarc(systemAgency):667.l",
          "manifestationMarc(systemAgency):667.i",
          "manifestationMarc(systemAgency):667.o",
          "manifestationMarc(systemAgency):667.u"
        ],
        url:"/search?q=${tag}"
      }]
    },
    aliases: {
      Subject: ["Tags"],
      Spog: ["Language"]
    },
    description: {
      Subject: {
        data: ["marc:001.a", "marc:001.c"],
        insert: "prepend"
      },
      Emnetal: {
        data: ["marc:088.a"],
        insert: "replace",
        url: "/search?q=${tag}"
      },
      "Skøn-/faglitteratur": {
        hidden: true
      },
      Emneord: {
        data: ["marc:631.a"],
        insert:"prepend",
        url:"/search?q=${tag}"
      }
    },
    detail: {
      Spog: {
        data: ["marc:001.a", "marc:001.c"],
        insert: "prepend"
      },
      Stemmer: {
        data: ["marc:509.a"]
      },
      "Stemmer forkortet": {
        data: ["marc:509.b"]
      },
      Indhold: {
        data: ["marc:795.a","marc:530.a"],
        type: "list",
        insert:"fallback"
      },
      "Indledende tekst": {
        "data": ["manifestationMarc(systemAgency):530.i", "marc:530.i"],
        "url":"/search?q=${tag}"
      },
      "Titel": {
        "data": ["manifestationMarc(systemAgency):530.t", "marc:530.t"],
        "type": "list",
        "url":"/search?q=${tag}"
      },
      "Ophavsangivelse": {
        "data": ["manifestationMarc(systemAgency):530.e", "marc:530.e"],
        "url":"/search?q=${tag}"
      },
      "Paralleltitel": {
        "data": ["manifestationMarc(systemAgency):530.p", "marc:530.p"],
        "type": "list",
        "url":"/search?q=${tag}"
      }
    }
  };

  const complexSearch = {
    terms: [{ label: "Marc 088a", term: "localclassification" }]
  };

  document.body.setAttribute("data-eonext-ext-covers", extendedCovers);
  document.body.setAttribute("data-eonext-ext-fields", JSON.stringify(extendedFields));
  document.body.setAttribute("data-eonext-ext-complex-search", JSON.stringify(complexSearch));

  const translationControls = "google_translate, drupal_translate";
  //const translationControls = "drupal_translate";
  const translationLanguages = {
    da: {
      name: "Danish",
      path:"/da/frontpage"
    },
    kl: {
      name: "Greenlandic",
      path: "/kl/frontpage"
    }
  };

  document.body.setAttribute("data-eonext-translation-type", translationControls);
  document.body.setAttribute("data-eonext-translation-languages", JSON.stringify(translationLanguages));

  const showSearchBranchSelection = "true";
  const showSearchSorting = "false";
  const searchLazyTypesLoading = "true";

  const branchesConfig = [{
    branchId:"DK-830630",
    title:"Aalborg"
  },{
    branchId:"DK-830480",
    title:"Aarhus"
  }];

  const blacklistedSearchBranches = "DK-830480";

  document.body.setAttribute("data-show-search-sorting", showSearchSorting);
  document.body.setAttribute("data-search-lazy-types-loading", searchLazyTypesLoading);

  document.body.setAttribute("data-show-search-branch-selection", showSearchBranchSelection);
  document.body.setAttribute("data-branches-config", JSON.stringify(branchesConfig));
  document.body.setAttribute("data-blacklisted-search-branches-config", blacklistedSearchBranches);

  document.body.setAttribute("data-blacklisted-reservation-groups", "an");

  const agencyConfig = { id: "911130" };
  document.body.setAttribute("data-agency-config", JSON.stringify(agencyConfig));

  document.body.setAttribute("data-opening-hours-sidebar-expanded", "10, 12, 24");
}
