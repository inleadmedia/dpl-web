import React, { useState } from "react";
import clsx from "clsx";
import {
  RecommendedMaterial,
  RecommendedMaterialProps,
} from "../recommended-material/RecommendedMaterial";

type AllowedDisplayAmount = 4 | 8 | 12 | 16 | 20 | 24 | 28 | 32;

export type MaterialGridProps = {
  title?: string;
  description?: string;
  selectedAmountOfMaterialsForDisplay: AllowedDisplayAmount;
  initialMaximumDisplay?: AllowedDisplayAmount;
  materials: RecommendedMaterialProps[];
  buttonText?: string;
  buttonLink?: string;
};

export const MaterialGrid: React.FC<MaterialGridProps> = ({
  title,
  description,
  selectedAmountOfMaterialsForDisplay,
  materials,
  buttonText,
  buttonLink,
  initialMaximumDisplay = 4,
}) => {
  const initialMaterialsToDisplay =
    selectedAmountOfMaterialsForDisplay > initialMaximumDisplay
      ? initialMaximumDisplay
      : selectedAmountOfMaterialsForDisplay;

  const [showAllMaterials, setShowAllMaterials] = useState(false);
  const [currentMaterialsDisplayed, setCurrentMaterialsDisplayed] = useState(
    initialMaterialsToDisplay,
  );

  const moreMaterialsThanInitialMaximum =
    selectedAmountOfMaterialsForDisplay > initialMaximumDisplay;

  function handleSetShowAllMaterials() {
    setShowAllMaterials(true);
    setCurrentMaterialsDisplayed(selectedAmountOfMaterialsForDisplay);
  }

  const titleClasses = clsx("material-grid__title", {
    "material-grid__title--no-description": !description,
  });

  return (
    <div className="material-grid">
      {(title || description) && (
        <div className="material-grid__text-wrapper">
          {title && <h2 className={titleClasses}>{title}</h2>}
          {description && (
            <p className="material-grid__description">{description}</p>
          )}
        </div>
      )}
      <ul className="material-grid__items">
        {materials
          .slice(0, currentMaterialsDisplayed)
          .map((material, index) => (
            <li key={index}>
              <RecommendedMaterial partOfGrid {...material} />
            </li>
          ))}
      </ul>
      {/* eslint-disable */}
      {/* Linter is going crazy with current ternary condition format */}
      {
        moreMaterialsThanInitialMaximum && !showAllMaterials && buttonText
          ? React.createElement(buttonLink ? "a" : "button", {
            className: "material-grid__show-more btn-primary btn-outline btn-medium btn-fit-content",
            "data-show-more": true,
            "aria-expanded": showAllMaterials ? "true" : "false",
            type: "button",
            "aria-controls": "material-grid__items",
            onClick: buttonLink ? undefined : () => handleSetShowAllMaterials(),
            href: buttonLink
          }, buttonText)
          : null
      }
      {/* eslint-enable */}
    </div>
  );
};
