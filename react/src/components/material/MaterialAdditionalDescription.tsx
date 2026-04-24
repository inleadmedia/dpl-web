import React, { useMemo } from "react";
import { useText } from "../../core/utils/text";
import { Work } from "../../core/utils/types/entities";
import HorizontalTermLine from "../horizontal-term-line/HorizontalTermLine";

export interface MaterialAdditionalDescriptionProps {
  work: Work;
  fieldsOptions: any;
}

const MaterialAdditionalDescription: React.FC<MaterialAdditionalDescriptionProps> = ({ work, fieldsOptions }) => {
  const t = useText();
  const data = useMemo(() => {
    if (!fieldsOptions)
      return {};

    return {
      label: fieldsOptions.label,
      body: fieldsOptions.merge([], fieldsOptions.getter(work), { outputType: "text" }),
      tags: (fieldsOptions.tags || []).map((tagData: any) => {
        return {
          label: tagData.label,
          tags: tagData.getter(work).filter(Boolean).map((tag: string) => {
            return {
              term: tag,
              url: new URL((tagData.url || "#").replace(/\$\{\s*tag\s*\}/ig, tag), window.location.href)
            };
          })
        };
      }).filter((tagData: any) => tagData.tags.length > 0)
    };
  }, [work, work.parsedExtraMarc, fieldsOptions]);

  if (!fieldsOptions || (!data.body && data.tags.length === 0))
    return null;

  return (
    <section className="material-description">
      {data.body && (
        <>
          <h2 className="text-header-h4 pb-24">
            {t(data.label)}
          </h2>

          <p className="text-body-large material-description__content">
            {data.body}
          </p>
        </>
      )}

      <div className="material-description__links mt-32">
        {
          data.tags.map((customField: any) => {
            return <HorizontalTermLine
              key={ customField.label }
              title={ customField.label }
              linkList={ customField.tags }
              dataCy={ customField.cy || "material-description-custom" }
            />
          })
        }
      </div>
    </section>
  );
};


export default MaterialAdditionalDescription;
