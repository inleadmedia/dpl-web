import React from "react";
import AutosuggestEditorial from "./AutosuggestEditorial";

interface Props {
  materialId: string;
  limit: number;
  filter?: string | string[];
  title: string;
  receiptIcon: string;
  expandIcon: string;
}

const MaterialEditorialDisclosure: React.FC<Props> = ({
  materialId,
  limit,
  filter,
  title,
  receiptIcon,
  expandIcon
}) => (
  <details
    className="disclosure text-body-large eonext-editorial-disclosure"
    data-cy="material-related-content-disclosure"
  >
    <summary className="disclosure__headline text-body-large">
      <div className="disclosure__icon bg-identity-tint-120">
        <img className="invert" src={receiptIcon} alt="" />
      </div>
      <h3 className="text-body-large disclosure__text">{title}</h3>
      <img className="disclosure__expand noselect" src={expandIcon} alt="" />
    </summary>
    <div className="autosuggest__editorial-suggestions-materials">
      <AutosuggestEditorial
        materialId={materialId}
        template="material-results"
        limit={limit}
        filter={filter}
      />
    </div>
  </details>
);

export default MaterialEditorialDisclosure;
