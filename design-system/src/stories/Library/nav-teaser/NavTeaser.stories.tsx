import { StoryFn, Meta } from "@storybook/react-webpack5";
import NavTeaser from "./NavTeaser";

export default {
  title: "Library / Nav teaser",
  component: NavTeaser,
  argTypes: {
    title: { control: "text" },
    subtitle: { control: "text" },
    teaserText: { control: "text" },
    backgroundImageSrc: { control: "text" },
    overlayBackgroundColor: { control: "color" },
  },
  args: {
    title: "Læseklub for børn",
    subtitle:
      "Børn har en tendens til at droppe fritidslæsningen omkring de 10 år. Med læsefamilieposerne får du inspiration til at få hele familien samlet omkring læsning.",
  },
  parameters: {
    design: {
      type: "figma",
      url: "https://www.figma.com/file/Zx9GrkFA3l4ISvyZD2q0Qi/Designsystem?type=design&node-id=434-6449&mode=design&t=BnLo07eCsytFa8Ik-4",
    },
  },
} as Meta<typeof NavTeaser>;

const Template: StoryFn<typeof NavTeaser> = (args) => <NavTeaser {...args} />;

export const Teaser = Template.bind({});

export const WithTeaserText = Template.bind({});
WithTeaserText.args = {
  teaserText:
    "Find inspiration til læsning, aktiviteter og arrangementer for hele familien.",
};

export const WithBackgroundImage = Template.bind({});
WithBackgroundImage.args = {
  backgroundImageSrc: "images/campaign_cover.jpg",
  title: "Værksteder i huset",
};

export const WithBackgroundImageAndTeaserText = Template.bind({});
WithBackgroundImageAndTeaserText.args = {
  backgroundImageSrc: "images/campaign_cover.jpg",
  title: "Værksteder i huset",
  teaserText:
    "Deltag i kreative værksteder og oplev nye fællesskaber i biblioteket.",
};

export const WithCustomOverlayColor = Template.bind({});
WithCustomOverlayColor.args = {
  backgroundImageSrc: "images/campaign_cover.jpg",
  title: "Værksteder i huset",
  teaserText:
    "Deltag i kreative værksteder og oplev nye fællesskaber i biblioteket.",
  overlayBackgroundColor: "#235881",
};

export const WithLightOverlayColor = Template.bind({});
WithLightOverlayColor.args = {
  backgroundImageSrc: "images/campaign_cover.jpg",
  title: "Værksteder i huset",
  teaserText:
    "Deltag i kreative værksteder og oplev nye fællesskaber i biblioteket.",
  overlayBackgroundColor: "#FEFAF1",
};
