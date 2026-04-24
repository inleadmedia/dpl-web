import type { Meta, StoryObj } from "@storybook/react";
import React from "react";
import DemoWayfinder from "./demo-wayfinder.entry";
import serviceUrlArgs, {
  argTypes as serviceUrlArgTypes
} from "../../core/storybook/serviceUrlArgs";
import globalTextArgs, {
  argTypes as globalTextArgTypes
} from "../../core/storybook/globalTextArgs";
import globalConfigArgs, {
  argTypes as globalConfigArgTypes
} from "../../core/storybook/globalConfigArgs";
import mappArgs, {
  argTypes as mappArgTypes
} from "../../core/storybook/mappArgs";

const meta: Meta<typeof DemoWayfinder> = {
  title: "Apps / Demo wayfinder",
  component: DemoWayfinder,
  argTypes: {
    ...serviceUrlArgTypes,
    ...globalTextArgTypes,
    ...globalConfigArgTypes,
    ...mappArgTypes
  }
} as Meta<typeof DemoWayfinder>;

export default meta;

type Story = StoryObj<typeof DemoWayfinder>;

export const Default: Story = {
  args: {
    ...serviceUrlArgs,
    ...globalTextArgs,
    ...globalConfigArgs,
    ...mappArgs
  }
};
