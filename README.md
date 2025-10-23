# Japanese Holiday🎉

We store Japanese holidays obtained from **Google Calendar’s official Japanese holiday feed** (`ja.japanese#holiday@group.v.calendar.google.com`) by year in **JSON** and **CSV** formats.

Data is stored for the past, current, and next five years, and each daily update keeps the dataset consistent in ISO 8601 format.

This project automatically updates data once per day through **GitHub Actions**, and the repository remains active through a monthly keep-alive workflow.

## How to use🤔
This project is hosted on [GitHub Pages](https://ennacx.github.io/jp-holiday/).

### Basic
To get basic information about national holidays and festival holidays for the past ***3 years*** (last year, this year, and next year), access `date.json` or `date.csv`.<br>
(Each version is separated into directories like `v1`, `v2`, ...)

Example:  
https://ennacx.github.io/jp-holiday/v1/date.json

### Separate national-holidays and festival-holidays
You can also get filtered lists for national holidays only, or for festival holidays only.

- National holidays only → https://ennacx.github.io/jp-holiday/v1/shu/date.json
- Festival holidays only → https://ennacx.github.io/jp-holiday/v1/sai/date.json

### Divide by year
To get a list divided by year, specify the directory for the year after the version.<br>
(Currently available for the preceding ***5 years***.)

Example:<br>
2024's Japanese holidays → https://ennacx.github.io/jp-holiday/v1/2024/date.json  
2024's national holidays only → https://ennacx.github.io/jp-holiday/v1/2024/shu/date.json  
2024's festival holidays only → https://ennacx.github.io/jp-holiday/v1/2024/sai/date.json

### Date format and timestamp format
Each `date.json` file is an object whose keys are **ISO 8601 formatted dates** (`YYYY-MM-DD`, string) and whose values are the holiday names.  
For CSV files, the first column contains the ISO 8601 date string and the second column contains the holiday name.

We also provide timestamp-based files (`ts.json`, `ts.csv`),  
where the key or first column is a **UNIX timestamp in seconds** (`integer`).

**(All UNIX timestamps are normalized to JST (UTC+9), consistent with Japan’s national calendar.)**

#### Example Date formatted JSON (`date.json`) structure:
```json
{
  "2025-01-01": "元日",
  "2025-01-13": "成人の日"
}
```

#### Example Timestamp formatted JSON (`ts.json`) structure:
```json
{
  "1735657200": "元日",
  "1736694000": "成人の日"
}
```

#### Example Date formatted CSV (`date.csv`) structure:
```csv
"2025-01-01","元日"
"2025-01-13","成人の日"
```

#### Example Timestamp formatted CSV (`ts.csv`) structure:
```csv
"1735657200","元日"
"1736694000","成人の日"
```

### Update frequency
- The data source is refreshed daily via **GitHub Actions**.
- Cached Google Calendar responses are persisted between runs to reduce API load.
- Cache validation is performed automatically using HTTP 304 (incremental update).
- Repository remains active with a scheduled monthly keep-alive commit.

## Directory tree🌱
```
v1/
|- 2020/
|    |- shu/
|    |    |- date.json
|    |    |- date.csv
|    |    |- ts.json
|    |    -- ts.csv
|    |- sai/
|    |    |- date.json
|    |    |- date.csv
|    |    |- ts.json
|    |    -- ts.csv
|    |
|    |- date.json
|    |- date.csv
|    |- ts.json
|    -- ts.csv
|
|- shu/
|    |- date.json
|    |- date.csv
|    |- ts.json
|    -- ts.csv
|- sai/
|    |- date.json
|    |- date.csv
|    |- ts.json
|    -- ts.csv
|
|- date.json
|- date.csv
|- ts.json
-- ts.csv
```

## License🧐
* [MIT](https://en.wikipedia.org/wiki/MIT_License)
* [CreativeCommons BY-SA](https://creativecommons.org/licenses/by-sa/4.0/)

## Metadata📝
- **Data source:** Google Calendar (Japanese Holiday feed)
- **Format:** ISO 8601 for dates, UNIX time for timestamps
- **Last update:** auto-generated daily via GitHub Actions
- **Maintainer:** [ennacx](https://github.com/ennacx)
