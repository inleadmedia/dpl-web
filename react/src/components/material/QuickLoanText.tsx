import React from "react";
import { useText } from "../../core/utils/text";
import { useConfig } from "../../core/utils/config";
import { Manifestation } from "../../core/utils/types/entities";
import {
  getAllPids,
  convertPostIdsToFaustIds
} from "../../core/utils/helpers/general";
import { useGetHoldings } from "../../apps/material/helper";

interface QuickLoanTextProps {
  manifestations: Manifestation[];
}

export const QuickLoanText: React.FC<QuickLoanTextProps> = ({ manifestations }) => {
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

  const quickLoanLibrariesMap: any = {};
  holdings.forEach(holding => {
    if (holding?.lmsPlacement?.sublocation?.sublocationId === "kvik" && holding?.branch?.title)
      quickLoanLibrariesMap[holding?.branch?.title] = true;
  });

  const quickLoanLibraries = Object.keys(quickLoanLibrariesMap);
  if (quickLoanLibraries.length === 0)
    return null;

  return <div className="mt-4 text-small-caption">
    { t("Kviklån - 14 dages lån:") } { quickLoanLibraries.join(", ") }
  </div>;
};
