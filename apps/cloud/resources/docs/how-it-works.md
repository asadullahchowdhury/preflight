# How it works

## What happens on a pull request

1. The GitHub Action starts a throwaway MySQL of your production version.
2. `php artisan preflight:check --base=origin/main` runs the migrations that already exist on your base branch, which rebuilds production's schema in that database.
3. It captures the SQL of the migrations your branch adds, in Laravel's pretend mode, so they are never executed.
4. The engine compares each statement with the schema (`SHOW CREATE TABLE`) and decides how MySQL will run it.
5. The verdict is posted as a comment, as annotations on the migration files and in the job summary. With Preflight Cloud, the Preflight App posts it as a check run instead.

## How MySQL runs a schema change

| Algorithm | What MySQL does                                  | Writes during the change       |
| --------- | ------------------------------------------------ | ------------------------------ |
| `INSTANT` | Changes only the table's metadata                | Continue                       |
| `INPLACE` | Rebuilds or indexes the table without copying it | Usually continue (`LOCK=NONE`) |
| `COPY`    | Copies every row into a new table                | **Blocked** until it finishes  |

Which one a change gets depends on the change, the table and the MySQL version. Some examples:

- `->string('status', 100)->change()` from `varchar(50)` crosses the 255-byte length boundary, so it **copies** the table.
- `->foreignId('user_id')->constrained()` on an existing table **copies** it unless foreign key checks are off.
- Adding a column is **instant** on MySQL 8.0.12+, except on tables with a FULLTEXT index, where it copies.
- Dropping a column is instant from 8.0.29, and an in-place rebuild before that.

## Severity

|             | Without table sizes                                | With production table sizes                              |
| ----------- | -------------------------------------------------- | -------------------------------------------------------- |
| **Danger**  | Blocks writes (COPY, `LOCK=SHARED`)                | Writes blocked for 10 seconds or more                    |
| **Warning** | Rebuilds the table, builds an index, or loses data | Writes blocked 1–10 seconds, or 5+ minutes of heavy work |
| **Safe**    | Instant or metadata only                           | Everything else                                          |

## Is it accurate?

The verdicts come from a deterministic rules engine, with no AI involved. Each rule is a test case, and the whole suite runs against real MySQL servers (8.0.12, 8.0.27, 8.0.28, 8.0.30, the latest 8.0 and 8.4). MySQL itself must accept the predicted algorithm and refuse the next stricter one, and the table must be rebuilt exactly when predicted.
