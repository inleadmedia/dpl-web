import React from "react";
import { useText } from "../../core/utils/text";
import { useConfig } from "../../core/utils/config";
import { getAllPids, convertPostIdsToFaustIds } from "../../core/utils/helpers/general";
import { useGetHoldings } from "../../apps/material/helper"

import { QuickLoanTextConstructor } from "./QuickLoanTextConstructor.jsx";

export const QuickLoanText = QuickLoanTextConstructor(React, {
  useText,
  useConfig,
  getAllPids,
  convertPostIdsToFaustIds,
  useGetHoldings
});
