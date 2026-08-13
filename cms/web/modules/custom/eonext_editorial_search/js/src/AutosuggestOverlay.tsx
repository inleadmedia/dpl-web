import React, { useState, useEffect, useRef, useCallback } from "react";
import AutosuggestEditorial, {
  EditorialSearchResult
} from "./AutosuggestEditorial";

interface Props {
  input: HTMLInputElement;
  limit: number;
  filter?: string | string[];
}

interface Rect {
  top: number;
  left: number;
  width: number;
  height: number;
}

const isAutosuggestOpen = (): boolean =>
  !!document.querySelector(".autosuggest-backdrop--open");

const getSiteHeader = (): HTMLElement | null =>
  document.querySelector<HTMLElement>("header.header");

const resetAutosuggestBackdrop = (backdrop: HTMLElement): void => {
  backdrop.style.removeProperty("top");
  backdrop.style.removeProperty("height");
  backdrop.style.removeProperty("bottom");
};

const clipAutosuggestBackdropBelowHeader = (): void => {
  const backdrop = document.querySelector<HTMLElement>(".autosuggest-backdrop");
  const header = getSiteHeader();
  if (!backdrop) {
    return;
  }

  if (!isAutosuggestOpen() || !header) {
    resetAutosuggestBackdrop(backdrop);
    return;
  }

  const top = Math.max(0, header.getBoundingClientRect().bottom);
  backdrop.style.top = `${top}px`;
  backdrop.style.height = `calc(100% - ${top}px)`;
  backdrop.style.bottom = "auto";
};

const AutosuggestOverlay: React.FC<Props> = ({ input, limit, filter }) => {
  const [query, setQuery] = useState<string>(input.value || "");
  const [nativeOpen, setNativeOpen] = useState<boolean>(isAutosuggestOpen());
  const [hits, setHits] = useState<number>(0);
  const [rect, setRect] = useState<Rect | null>(null);
  const prevNativeOpen = useRef<boolean>(nativeOpen);
  const overlayRef = useRef<HTMLDivElement>(null);

  const container =
    (input.closest(".header__menu-search") as HTMLElement) || input;

  const getDropdown = useCallback(
    (): HTMLElement | null =>
      container.querySelector<HTMLElement>(".autosuggest.autosuggest--open"),
    [container]
  );

  const sync = useCallback(() => {
    const open = isAutosuggestOpen();
    setNativeOpen(open);

    if (open) {
      setQuery(input.value);
    }

    const dropdown = getDropdown();
    if (dropdown) {
      const r = dropdown.getBoundingClientRect();
      if (r.width > 0) {
        setRect({
          top: r.top,
          left: r.left,
          width: r.width,
          height: Math.max(r.height, 0)
        });
        return;
      }
    }

    if (!open) {
      return;
    }

    const c = container.getBoundingClientRect();
    setRect({ top: c.bottom, left: c.left, width: c.width, height: 0 });
  }, [getDropdown, container, input]);

  useEffect(() => {
    const onInput = () => {
      setQuery(input.value);
      sync();
    };

    const onFocus = () => {
      setQuery(input.value);
      sync();
    };

    input.addEventListener("input", onInput);
    input.addEventListener("focus", onFocus);
    window.addEventListener("resize", sync);
    window.addEventListener("scroll", sync, true);

    const observer = new MutationObserver(sync);
    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ["class"],
      subtree: true,
      childList: true
    });
    observer.observe(container, {
      attributes: true,
      attributeFilter: ["class", "style"],
      childList: true,
      subtree: true
    });

    sync();

    return () => {
      input.removeEventListener("input", onInput);
      input.removeEventListener("focus", onFocus);
      window.removeEventListener("resize", sync);
      window.removeEventListener("scroll", sync, true);
      observer.disconnect();
    };
  }, [input, sync, container]);

  useEffect(() => {
    if (prevNativeOpen.current && !nativeOpen) {
      setHits(0);
    }
    prevNativeOpen.current = nativeOpen;
  }, [nativeOpen]);

  useEffect(() => {
    clipAutosuggestBackdropBelowHeader();

    const onLayoutChange = () => clipAutosuggestBackdropBelowHeader();
    window.addEventListener("resize", onLayoutChange);
    window.addEventListener("scroll", onLayoutChange, true);

    return () => {
      window.removeEventListener("resize", onLayoutChange);
      window.removeEventListener("scroll", onLayoutChange, true);
      const backdrop = document.querySelector<HTMLElement>(".autosuggest-backdrop");
      if (backdrop) {
        resetAutosuggestBackdrop(backdrop);
      }
    };
  }, [nativeOpen, rect]);

  const onFound = useCallback((res: EditorialSearchResult) => {
    setHits(res && Array.isArray(res.results) ? res.results.length : 0);
  }, []);

  const visible =
    nativeOpen && hits > 0 && rect !== null && rect.width > 0;

  useEffect(() => {
    document.body.classList.toggle("eonext-editorial-autosuggest-open", visible);
    return () => {
      document.body.classList.remove("eonext-editorial-autosuggest-open");
    };
  }, [visible]);

  useEffect(() => {
    const overlay = overlayRef.current;
    const dropdown = getDropdown();

    if (!visible || !overlay || !dropdown) {
      if (dropdown) {
        dropdown.style.minHeight = "";
      }
      return;
    }

    const syncColumnHeight = () => {
      const overlayHeight = overlay.getBoundingClientRect().height;
      const dropdownHeight = rect?.height ?? 0;
      dropdown.style.minHeight = `${Math.max(overlayHeight, dropdownHeight)}px`;
    };

    syncColumnHeight();

    const observer = new ResizeObserver(syncColumnHeight);
    observer.observe(overlay);

    return () => {
      observer.disconnect();
      dropdown.style.minHeight = "";
    };
  }, [visible, rect, getDropdown, hits, query]);

  const style: React.CSSProperties = rect
    ? {
        position: "fixed",
        top: `${rect.top}px`,
        left: `${rect.left + rect.width / 2}px`,
        width: `${rect.width / 2}px`,
        minHeight: `${Math.max(rect.height, 200)}px`,
        display: visible ? "block" : "none"
      }
    : { display: "none" };

  return (
    <div
      ref={overlayRef}
      className={`eonext-editorial-overlay${visible ? " eonext-editorial-overlay--visible" : ""}`}
      style={style}
    >
      <div className="eonext-editorial-overlay__heading">
        Redaktionelt Indhold
      </div>
      <AutosuggestEditorial
        query={query}
        template="suggestion"
        limit={limit}
        filter={filter}
        onFound={onFound}
      />
    </div>
  );
};

export default AutosuggestOverlay;
