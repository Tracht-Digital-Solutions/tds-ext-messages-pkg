import { useEffect, useState } from "react";
import { Skeleton } from "@tracht-digital-solutions/tds-shared/components";

/**
 * "Neue Nachrichten" widget body. Fetches the unread count from the manifest's
 * dataEndpoint (`/messages/summary`). Relative fetch with credentials.
 */
export default function UnreadMessagesCount() {
  const [unread, setUnread] = useState<number | null>(null);
  useEffect(() => {
    let alive = true;
    fetch("/messages/summary", { credentials: "include" })
      .then((r) => (r.ok ? r.json() : { unread: 0 }))
      .then((d) => alive && setUnread(Number(d.unread ?? 0)))
      .catch(() => alive && setUnread(0));
    return () => {
      alive = false;
    };
  }, []);
  return <p className="tds-widget__metric" aria-busy={unread === null}>
      {unread === null ? <Skeleton width="3ch" height="1.75rem" /> : unread}
    </p>;
}
