export const QuickLoanTextConstructor = (React, externalLibraries) => {
  const useText = externalLibraries.useText;
  const useConfig = externalLibraries.useConfig;
  const getAllPids = externalLibraries.getAllPids;
  const convertPostIdsToFaustIds = externalLibraries.convertPostIdsToFaustIds;
  const useGetHoldings = externalLibraries.useGetHoldings;

  return function QuickLoanText({ manifestations }) {
    const t = useText();
    const config = useConfig();

    const pids = getAllPids(manifestations);
    const faustIds = convertPostIdsToFaustIds(pids);
    const { data, isLoading, isError } = useGetHoldings({
      faustIds,
      config,
      blacklist: "availability"
    });

    const holdings = data && data[0] && data[0].holdings;
    if (!holdings || holdings.length === 0)
      return null;

    const quickLoanLibrariesMap = {};
    holdings.forEach((holding) => {
      if (
        holding?.lmsPlacement?.sublocation?.sublocationId === "kvik"
        && holding?.branch?.title
        && holding?.materials.some(material => material.available)
      ) {
        quickLoanLibrariesMap[holding?.branch?.title] = true;
      }
    });

    const quickLoanLibraries = Object.keys(quickLoanLibrariesMap);
    if (quickLoanLibraries.length === 0)
      return null;

    // Inline style is used to leave the Quick loan text component without the requirement to use external styles
    return <div className="mt-4 text-small-caption" style={{ width: "100%" }}>
      { t("Kviklån - 14 dages lån:") } { quickLoanLibraries.join(", ") }
    </div>;
  };
};
