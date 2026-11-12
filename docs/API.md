# Puzzle Garden API v1

Base URL: `https://missedit.chain-reaction-game.com/api/v1`

The API supports native game clients. It uses Laravel Sanctum bearer tokens, not browser cookies or CSRF tokens. Create an account on the website first. For local development, use the URL of your local Laravel server with `/api/v1` appended.

## Authentication

Players can log into the website and select **Game API** (`/account/tokens`) to create/revoke device tokens. A plaintext token is shown only when created; the database stores its SHA-256 hash. Tokens expire after 90 days and allow publishing/removing the owner's puzzles. There are up to 10 active tokens per account. Never embed a shared publisher token in a released game: each player uses their own account and token.

Alternatively, the game can obtain a token using the player's existing credentials:

```http
POST /api/v1/tokens
Content-Type: application/json
Accept: application/json

{"email":"player@example.com","password":"the player's password","name":"Pip on my desktop"}
```

Successful response (`201 Created`, `Cache-Control: no-store`):

```json
{
  "token_type": "Bearer",
  "token": "TOKEN_RETURNED_ONLY_ONCE",
  "expires_at": "2027-02-09T18:00:00+00:00"
}
```

The expiry above is illustrative. Use the actual returned timestamp. Store the token in the platform's credential storage; do not persist the password. Send authenticated requests with:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

`GET /me` checks a token and returns `{"data":{"name":"Creator name"}}`. `DELETE /tokens/current` revokes the current token (`204 No Content`). Expired/revoked tokens receive `401`; the player should sign in or create a new token. There is no refresh-token mechanism.

## Endpoints

All paths below are relative to `/api/v1`. `{id}` is the UUID `data.id` returned by the API, not the game's internal `Id` property.

| Method | Path | Authentication | Result |
| --- | --- | --- | --- |
| POST | `/tokens` | Email/password in body | New bearer token |
| POST | `/players` | Public, `name` in body | New name-only player and bearer token |
| GET | `/me` | Bearer | Current creator name |
| PATCH | `/me` | Bearer, `puzzles:write` ability | Change the creator name shown on all of this account's puzzles |
| DELETE | `/tokens/current` | Bearer | Revoke this device token |
| GET | `/puzzles` | Public | Paginated puzzle metadata |
| GET | `/puzzles/{id}` | Public | Metadata plus original `puzzle_json` string |
| GET | `/puzzles/{id}/download` | Public | Original JSON file, increments downloads |
| POST | `/puzzles/{id}/clears` | Public | A player landed HOME on a downloaded copy; increments `clears` (once per address per puzzle a day) |
| GET | `/me/puzzles` | Bearer | Current user's paginated puzzles |
| POST | `/puzzles` | Bearer, `puzzles:write` ability | Publish puzzle; return metadata/share URL |
| DELETE | `/puzzles/{id}` | Bearer, `puzzles:write` ability | Remove an owned puzzle (`204`) |
| POST | `/reports` | Public | Game error reports, forwarded to Logalyzer (`202`) |

## Game error reports

`POST /api/v1/reports` takes error reports from the game and forwards them to Logalyzer with the server's own token (see the README's Logalyzer section). Nothing is stored on this server.

```json
{
  "environment": "production",
  "context": {"version": "0.151.0", "platform": "Android", "os_version": "15", "device": "Pixel 7", "engine": "4.7.2-stable (official)", "screen": "1080x2400", "scene": "Level 15"},
  "entries": [
    {"severity": "error", "message": "NullReferenceException: ...", "source_file": "Board.cs", "line_number": 120, "count": 3, "trace": "at MissedIt.Board.Shoot() ...", "occurred_at": "2026-11-12T11:21:13Z"}
  ]
}
```

- `environment`: `production` or `development`.
- `context`: only the keys shown are kept (each a short string); anything else is dropped.
- `entries`: 1–20, each `warning`, `error` or `critical`, message up to 2,000 characters, trace up to 4,000. `count` is how many times the game saw that error.
- The reply is `202` with `accepted` and `forwarded` (false when Logalyzer isn't configured). Bad input is `422`.
- Limited to 10 a minute and 200 a day per IP address.

## Share from the game without an account

The game's **Share online** uses name-only players, so players never need a website account:

1. The first share sends `POST /api/v1/players` with `{"name": "Fern"}` (1–40 characters, not blank). The reply is `201` with `token_type`, `token`, `expires_at` and `name`. The game keeps the token and the name on the device.
2. When the player shares under a different name, the game sends `PATCH /api/v1/me` with the new name. The name changes on all of that player's puzzles.
3. The game uploads with `POST /api/v1/puzzles` as below. If the token is rejected (`401`, for example after it expires in 90 days), the game creates a new player once and retries.

A player is an ordinary user with a placeholder email (`player-<uuid>@players.missedit.invalid`) and a random password, so it can never sign in on the website. New players are limited to 3 a minute and 10 a day per IP address.

## Upload from the game

Send the game's **original serialized export as a string** in the `puzzle_json` field. Do not parse and reserialize it into a nested JSON object: keeping its original text preserves numeric precision, formatting, and replay hashes.

```http
POST /api/v1/puzzles
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
Accept: application/json

{
  "puzzle_json": "{\"Version\":1,\"Id\":\"...\",\"Title\":\"My sky garden\",...}",
  "difficulty": "tricky",
  "notes": "Try skimming the left side."
}
```

The abbreviated puzzle text above is illustrative, not a valid export. Use a complete `WorkshopDocument.Serialize()` result. Required `difficulty` is `gentle`, `tricky`, or `expert`. Optional `notes` has a 2,000-character limit. Title, objective, replay data, and mechanisms come from the export. The authenticated account supplies ownership; a client cannot choose another creator.

Alternatively, send `multipart/form-data` with a `puzzle_file` part and the same metadata. Send exactly one of `puzzle_json` or `puzzle_file`. Exports are limited to **100,000 bytes**, matching the website. Invalid files return `422` with an `errors.puzzle_json` entry. The API shares the website's structural validation; physics and replay verification remain in the game.

On creation, the API returns `201`, a `Location` header pointing to the metadata endpoint, and:

```json
{
  "data": {
    "id": "63e7b2a3-b846-42f9-9796-787aa8702b2f",
    "title": "Borrow the bend",
    "brief": "Deliver Pip HOME.",
    "notes": "Try skimming the left side.",
    "difficulty": "tricky",
    "mechanics": ["Gravity"],
    "has_replay": false,
    "downloads": 0,
    "clears": 0,
    "sha256": "SHA256_OF_ORIGINAL_EXPORT",
    "creator": {"name": "Player"},
    "created_at": "2026-11-11T18:00:00+00:00",
    "share_url": "https://missedit.chain-reaction-game.com/puzzles/63e7b2a3-b846-42f9-9796-787aa8702b2f",
    "api_url": "https://missedit.chain-reaction-game.com/api/v1/puzzles/63e7b2a3-b846-42f9-9796-787aa8702b2f",
    "download_url": "https://missedit.chain-reaction-game.com/api/v1/puzzles/63e7b2a3-b846-42f9-9796-787aa8702b2f/download"
  },
  "created": true
}
```

Display or copy `data.share_url` after upload. Retrying the exact same export under the same account returns **200** with `created: false` and the existing puzzle. It does not overwrite existing notes or difficulty. Downloads reproduce the submitted bytes exactly; `data.sha256` can be checked client-side. `has_replay` means replay data was included, not that the server verified the solution.

### C# upload example

For a Godot C# project, an asynchronous `HttpClient` call can send the exported string without modifying the puzzle:

```csharp
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;

// Reuse this client for the lifetime of the game/service, rather than per upload.
static readonly HttpClient PuzzleApi = new()
{
    BaseAddress = new Uri("https://missedit.chain-reaction-game.com/api/v1/"),
    Timeout = TimeSpan.FromSeconds(30)
};

static async Task<string> SharePuzzleAsync(
    string exportedJson, string playerToken, CancellationToken cancellationToken)
{
    using var request = new HttpRequestMessage(HttpMethod.Post, "puzzles");
    request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", playerToken);
    request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
    request.Content = JsonContent.Create(new
    {
        puzzle_json = exportedJson,
        difficulty = "tricky"
    });
    using var response = await PuzzleApi.SendAsync(request, cancellationToken);
    var body = await response.Content.ReadAsStringAsync(cancellationToken);
    if (!response.IsSuccessStatusCode)
        throw new HttpRequestException($"Puzzle upload failed ({(int)response.StatusCode}): {body}");
    using var result = JsonDocument.Parse(body);
    return result.RootElement.GetProperty("data").GetProperty("share_url").GetString()!;
}
```

Pass `Document.Serialize()` as `exportedJson`. Connect this method to the game's UI and show validation errors without discarding the current local puzzle. The server changes do not automatically modify the Godot game. Production client code should handle the statuses below and keep token values out of logs.

## Browse and download

`GET /puzzles` and `GET /me/puzzles` accept:

| Parameter | Values |
| --- | --- |
| `q` | Title, objective, or creator search, at most 100 characters |
| `difficulty` | `gentle`, `tricky`, `expert` |
| `mechanic` | `Gravity`, `Repulsor`, `Accelerator`, `Orbiter`, `Capture`, `Bumper`, `Portals`, `Surfaces`, `Rivers`, `Blossoms` |
| `sort` | `newest` (default), `oldest`, `popular` (most downloads), `cleared` (most clears) |
| `per_page` | 1–50 (default 24) |
| `page` | Positive page number |

Responses contain `data`, pagination `links`, and `meta` (`current_page`, `last_page`, `per_page`, `total`, etc.). Listings omit the large puzzle export; fetch its detail or download URL when selected. Only the download endpoint increments the download count.

`POST /puzzles/{id}/clears` takes no body and returns `{"data":{"id":"…","clears":5}}`. The game sends it once when a player lands HOME on an unchanged downloaded copy. Each address counts once per puzzle a day (a repeat gets `429`), and at most 60 clears an hour.

## Errors and limits

API errors are JSON even if the client omits the `Accept` header:

- `401`: missing, invalid, expired, or revoked bearer token.
- `403`: token lacks the write ability, or the puzzle belongs to another account.
- `404`: puzzle no longer exists.
- `422`: invalid login credentials, request fields, puzzle format, or active-token limit. Read `message` and the field-to-message-arrays map in `errors`.
- `429`: rate limit reached; wait for the `Retry-After` header before retrying.
- `413`: web-server/PHP request body limit exceeded; the upstream's error body may be HTML.

Token login allows 5 attempts/minute per IP. Public API browsing/details/downloads share a 60/minute IP limit. Authenticated operations share a 60/minute per-user limit, with uploads capped at 10/minute per user and 30/hour per IP across web and API. Name-only players allow 3/minute and 10/day per IP. Website token creation allows 5/minute per user. Upload retry deduplication applies to the same account and exact file bytes, not arbitrary client idempotency keys.

## Deployment

```bash
cd /var/www/sites/missed-it/server
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

The new migration adds `personal_access_tokens`. Keep the existing `.env` and database. This workspace has already applied the migration when delivered. No new Nginx rule is required: `/api/v1/*` routes through Laravel's existing front controller. `auth:sanctum` uses bearer tokens only (`sanctum.guard` is empty); browser sessions continue using the separate web guard. Expired tokens can be cleaned periodically with `php artisan sanctum:prune-expired --hours=24`; expiry enforcement works without a scheduled cleanup job.
