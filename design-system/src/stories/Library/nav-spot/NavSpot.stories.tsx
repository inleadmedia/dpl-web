import { StoryFn, Meta } from "@storybook/react-webpack5";

import NavSpot from "./NavSpot";
import ImageCredited from "../image-credited/ImageCredited";

const media = (
  <ImageCredited src="https://images.unsplash.com/photo-1585779034823-7e9ac8faec70?q=80&w=3024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" />
);

export default {
  title: "Library / Nav-spot (Navigationsmodul)",
  component: NavSpot,
  argTypes: {
    variant: {
      control: false,
    },
    title: {
      control: "text",
      type: "string",
    },
    subtitle: {
      control: "text",
      type: "string",
    },
    media: {
      control: false,
    },
    buttonLabel: {
      control: "text",
      type: "string",
    },
    imageAlignment: {
      control: "radio",
      options: ["left", "right"],
    },
    backgroundColor: {
      control: "color",
    },
  },
  args: {
    title: "Bøger som har gjort en forskel for romanens udvikling",
    subtitle: "Stine Pilgaard vinder De Gyldne Laurbær",
    media,
    url: "#",
    buttonLabel: "Læs mere",
    imageAlignment: "left",
  },
  parameters: {
    design: {
      type: "figma",
      url: "https://www.figma.com/file/Zx9GrkFA3l4ISvyZD2q0Qi/Designsystem?type=design&node-id=1958-7664&mode=design&t=nK04fkaFk3f9pafj-4",
    },
  },
} as Meta<typeof NavSpot>;

const Template: StoryFn<typeof NavSpot> = (args) => <NavSpot {...args} />;

const Large = Template.bind({});
Large.args = {
  variant: "large",
  imageAlignment: "right",
  backgroundColor: "#79B485",
  title: "KØB ET GAVEKORT",
  subtitle: "Den perfekte gave til gode oplevelser",
  buttonLabel: "KØB",
};

const LargeImageLeft = Template.bind({});
LargeImageLeft.args = {
  variant: "large",
  imageAlignment: "left",
  backgroundColor: "#79B485",
};

const LargeImageRight = Template.bind({});
LargeImageRight.args = {
  variant: "large",
  imageAlignment: "right",
  backgroundColor: "#79B485",
};

const Medium = Template.bind({});
Medium.args = {
  variant: "medium",
};

const Small = Template.bind({});
Small.args = {
  variant: "small",
};

export { Large, LargeImageLeft, LargeImageRight, Medium, Small };
