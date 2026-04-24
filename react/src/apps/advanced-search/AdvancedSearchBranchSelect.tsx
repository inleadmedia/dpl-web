import React, { useMemo } from "react";
import IconExpand from "@danskernesdigitalebibliotek/dpl-design-system/build/icons/collection/ExpandMore.svg";

import { useText } from "../../core/utils/text";

export type AdvancedSearchBranchSelectProps = {
  branchId?: string;
  onChange?: (branchId: string) => void;
};

export type Branch = {
  branchId?: string
  title?: string
};

export function useCMSBranches() {
  const branches = useMemo(() => {
    let branchSelectorEnabled = (document.querySelector("[data-show-search-branch-selection]")?.getAttribute("data-show-search-branch-selection") || "") === "true";
    if (branchSelectorEnabled === false)
      return [];

    let branchesForSelect = JSON.parse(document.querySelector("[data-branches-config]")?.getAttribute("data-branches-config") || "[]");
    let excludedBranches = (document.querySelector("[data-blacklisted-search-branches-config]")?.getAttribute("data-blacklisted-search-branches-config") || "")
      .split(",")
      .map((branch: string) => branch.trim())
      .filter(Boolean);

    if (excludedBranches.length !== 0) {
      branchesForSelect = branchesForSelect.filter((branch: any) => {
        return excludedBranches.includes(branch.branchId) === false;
      });
    }

    return branchesForSelect;
  }, [document.querySelector("[data-branches-config]")]);

  return branches;
}

const AdvancedSearchBranchSelect: React.FC<AdvancedSearchBranchSelectProps> = ({ onChange, branchId }) => {
  const t = useText();
  const branches = useCMSBranches();

  if (branches.length === 0)
    return null;

  return <section className="advanced-search__filters">
    <div className="dpl-cms__branch-select input-with-dropdown">
      <div className="dropdown dropdown--grey-borders input-with-dropdown__dropdown">
        <select
          className="dropdown__select dropdown__select--inline focus-styling"
          aria-label="input field dropdown"
          value={branchId}
          onChange={(e) => onChange && onChange(e.target.value)}
        >
          <option value="">{ t("searchInAllBranchesText") }</option>
          { branches.map((branch: any) => {
            return <option key={ branch.branchId } value={ branch.branchId }>{ branch.title }</option>
          }) }
        </select>
        <div className="dropdown__arrows dropdown__arrows--inline">
          <img className="dropdown__arrow" src={IconExpand} alt="" />
        </div>
      </div>
    </div>
  </section>;
};

export default AdvancedSearchBranchSelect;
