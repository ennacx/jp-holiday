# Japanese Holiday🎉

We store Japanese holidays obtained from Google by year in JSON and CSV formats.

## How to use
This project is hosted on GitHub Pages.<br>
[GitHub Pages](https://ennacx.github.io/jp-holiday/)

To get basic information about public holidays and festivals for the past three years, including last year, this year, and next year, access ```date.json``` or ```date.csv```.<br>
(Each version is separated into a directory. ```v1```, ```v2```...)

Ex. https://ennacx.github.io/jp-holiday/v1/date.json

We also provide a list filtered by national holidays only, and a file filtered by festival holidays only.

National holidays only: https://ennacx.github.io/jp-holiday/v1/shu/date.json<br>
Festival holidays only: https://ennacx.github.io/jp-holiday/v1/sai/date.json

To get a list divided by year, specify the directory for the year after the version.<br>
(Data is available for the preceding 5 years.)

Ex.<br>
2024 japanese holidays: https://ennacx.github.io/jp-holiday/v1/2024/date.json<br>
2024's national holidays only: https://ennacx.github.io/jp-holiday/v1/2024/shu/date.json<br>
2024's festival holidays only: https://ennacx.github.io/jp-holiday/v1/2024/sai/date.json

The contents of file A are objects whose keys are in the format ```'YYYY-MM-DD'``` and whose values are the names of holidays
(In the case of CSV, the first column contains the dates and the second column contains the names of holidays.).<br>
But we have also prepared a file whose keys are in the format of a timestamp (```int```).
When using this, please change ```date.json``` to ```ts.json```.

## Tree
```
/
|- 2020
|    |- shu
|    |    |- date.json
|    |    |- date.csv
|    |    |- ts.json
|    |    -- ts.csv
|    |- sai
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
|- shu
|    |- date.json
|    |- date.csv
|    |- ts.json
|    -- ts.csv
|- sai
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

## License
[MIT](https://en.wikipedia.org/wiki/MIT_License)

[CreativeCommons BY-SA](https://creativecommons.org/licenses/by-sa/4.0/)