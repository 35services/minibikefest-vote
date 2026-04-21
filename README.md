# Minibikefest — Best Bike of Show

A simple PHP voting site for the Minibikefest bike show.

## Files

| File | Purpose |
|------|---------|
| `index.php` | Public voting page |
| `admin.php` | Admin dashboard |
| `config.php` | DB credentials & admin secret |
| `contestants.md` | List of contestants (one `# Title` per line) |

## Setup

1. Upload all files to your PHP host.
2. Edit `config.php`:
   - Set `DB_PASS` to your database password.
   - Set `ADMIN_SECRET` to a long, random string you'll keep private.
3. Edit `contestants.md` with the actual bike names (one `# Name` heading per line).
4. Visit `index.php` — tables are created automatically on first load.

## Adding / changing contestants

Edit `contestants.md`. New entries are picked up automatically.  
After editing, click **Sync Contestants from MD** in the admin panel to ensure the DB is up to date.

## Admin panel

Access: `admin.php?secret=YOUR_ADMIN_SECRET`

- **Toggle voting open/closed** — controls whether the public can cast votes.
- **Sync Contestants** — re-reads `contestants.md` and inserts missing entries.
- **Delete All Votes** — wipe test votes before the real event starts.
- **Results table** — live vote counts with percentages.
- **Vote log** — last 100 votes with IP address and timestamp.

## How voting works

- The contestant list is shuffled randomly for every visitor.
- Voting requires no account or email — completely anonymous.
- A cookie is set on vote to prevent double voting from the same browser.
- The voter's IP is stored for abuse tracking only.
- Results are hidden until after the visitor votes (or voting is closed).

## Database

- Host: `mariadb.in-berlin.de:3306`
- Database: `35services_minibikefest`
- Tables are created automatically by `initDB()` on every page load.

## Requirements

- PHP 8.0+ with PDO and `pdo_mysql` extension
- MariaDB / MySQL 5.7+
