# Watch Session Tracker

Proof-of-concept real-time watch session tracking service for FloSports.

This service ingests viewer events from a player SDK, tracks active watch sessions, and exposes query endpoints for active viewer counts and session details.

The goal of this implementation is to satisfy the core requirements of the PRD with a simple, understandable architecture that is easy to discuss and extend.

---
## Important Commands
Spin up the environment:

```bash
docker compose up --build -d
```
That should create a SQLite database w/schema and start the server.

Run Unit and Functional Tests:
```bash
docker compose exec php php bin/phpunit --configuration=phpunit.xml.dist
```

## Planned API Design

### 1. Ingest Viewer Event

```http request
POST /api/viewer-events
```
Accepts viewer events from the player SDK.

Example request body:

```json
{
  "sessionId": "abc-123",
  "userId": "user-456",
  "eventType": "heartbeat",
  "eventId": "evt-789",
  "eventTimestamp": "2026-02-10T19:32:15.123Z",
  "receivedAt": "2026-02-10T19:32:15.450Z",
  "payload": {
    "eventId": "event-2026-wrestling-finals",
    "position": 1832.5,
    "quality": "1080p"
  }
}
```
Expected response shape:

```json
{
  "status": "accepted",
  "sessionId": "abc-123",
  "eventId": "evt-789"
}
```

If a duplicate event is received:

```json
{
  "status": "duplicate_ignored",
  "sessionId": "abc-123",
  "eventId": "evt-789"
}
```

### 2. Active Sessions Count for an Event

```http request
GET /api/events/{eventId}/active-sessions
```

Returns the current number of active watch sessions for the given event.

Expected response shape:

```json
{
  "eventId": "event-2026-wrestling-finals",
  "activeSessionCount": 42,
  "asOf": "2026-02-10T19:33:00Z",
  "activeWindowSeconds": 45
}
```

### 3. Session Details

```http request
GET /api/sessions/{sessionId}`
```
Returns current session details for a given session ID, including duration so far, current state, and received events.

Expected response shape:

```json
{
  "sessionId": "abc-123",
  "userId": "user-456",
  "eventId": "event-2026-wrestling-finals",
  "durationSeconds": 120,
  "currentState": "active",
  "events": [
    {
      "eventId": "evt-001",
      "eventType": "start",
      "eventTimestamp": "2026-02-10T19:32:15.123Z"
    },
    {
      "eventId": "evt-002",
      "eventType": "heartbeat",
      "eventTimestamp": "2026-02-10T19:32:45.123Z"
    }
  ]
}
```
---
## Assumptions
Because the PRD intentionally leaves some behaviors open, I am making the following assumptions:
- eventId uniquely identifies an incoming SDK event and can be used for idempotent ingestion / duplicate protection.
- A session belongs to a single user and a single streamed event.
- A session is considered active if its latest activity is within a 45-second window.
- Session duration is calculated as the difference between the first known event timestamp and the latest known event timestamp.
- Events may arrive out of order, so older events should not overwrite the newer session snapshot state.
- Heartbeats should not revive sessions that have already ended.
- All timestamps are treated as UTC.

### Datetime consistency assumption

The incoming API contract uses the PRD timestamp format:

```
2026-02-10T19:32:15.123Z
```
I plan to keep that format consistent at the API boundary and in raw event storage.

Internally, timestamp values used for querying and comparisons will be normalized consistently so that session activity checks and time-based filters behave predictably in SQLite. The key goal is to avoid mixing multiple timestamp formats across ingestion, persistence, and query logic.

If this were a production system, I would likely standardize even more aggressively, for example by storing epoch timestamps or a single database-friendly UTC format everywhere.

---
## Tradeoffs
- Didn't use Doctrine ORM because I wanted to keep the architecture simple and easy to reason about. For a proof-of-concept, v1 I felt it wasn't necessary to make Doctrine entities and manage those with the ORM.
- I didn't use DTOs and the ViewerEventIngestionService is bloated with validation and other domain logic I would distribute in Hydrator or Transformer classes.

- I chose SQLite because the PRD explicitly pushed for a lightweight v1 and Symfony is request-scoped, so in-memory state would not be shared across requests. I gained simplicity, easy setup, and persistence across requests, but gave up production-grade concurrency and throughput. At larger scale, I’d likely move to Postgres, Redis, or a streaming architecture.
- Raw events in watch_session_events, current session state in watch_sessions, I gained fast reads for active session counts and session details, but took on the complexity of maintaining a derived snapshot during ingestion. It’s a simpler version of an event-log-plus-projection approach.
- I processed events directly in the request path. I gained clarity and easier reasoning for a v1, but I did not fully solve spike durability or high-throughput buffering. In production, I’d likely put a queue or log in front of the write path.
- I prioritized unit tests around ingestion/session logic and a few functional endpoint tests. I gained confidence in the highest-risk business logic within the timebox, but did not try to build an exhaustive test suite.
- I defined active sessions using a 45-second threshold because heartbeats arrive every 30 seconds. I gained a simple, explainable approximation of real-time activity, but this does not guarantee exact viewer counts at every second. It’s a pragmatic compromise for a v1 dashboard metric.
---

## Why SQLite?
I chose SQLite for this implementation because the PRD explicitly encourages keeping storage lightweight.

Symfony runs in a request/response model, so in-memory state is not naturally shared across requests. Because of that, using an in-memory store for active sessions would be fragile unless additional infrastructure were introduced.

SQLite provides a good middle ground for this exercise:

- lightweight and easy to set up

- persistent across requests

- sufficient for a proof of concept

- keeps the architecture simple and easy to reason about

For a production-scale version of this system, I would likely move to a more scalable datastore and introduce asynchronous ingestion or streaming infrastructure to handle spikes more safely.

---
## Initial Storage Model

I currently plan to use two tables:

`watch_session_events`

Stores the raw event stream for auditing, debugging, and session history retrieval.

`watch_sessions`

Stores the latest known session snapshot for efficient reads, especially active session counts.

This is somewhat event-log / projection inspired, but intentionally simpler than a full event-sourced system.

---
## What I Would Do Next in a Production Version

If this system needed to go beyond a v1 proof of concept, I would likely explore:

- durable queue-based ingestion

- a more scalable database

- stronger replay / recovery guarantees

- better observability and metrics

- load testing and performance tuning
