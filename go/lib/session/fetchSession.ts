import { getBaseURL } from "@/lib/config/getBaseURL"

import goConfig from "../config/goConfig"
import { TSessionData } from "./session"

export const loadSession = async () => {
  // By using an absolute url we make sure that we can fetch the session both client and server side.
  const response = await fetch(`${getBaseURL()}/${goConfig("routes.session")}`)
  if (!response.ok) {
    // wait for response body, in other case it will stuck at NodeJS and will cause the memory leak.
    // https://github.com/vercel/next.js/issues/43493 - closed, but with no resolve
    await response.arrayBuffer()

    throw new Error("Failed to fetch session")
  }

  return (await response.json()) as TSessionData
}
