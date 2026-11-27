# Pohoria (geomorfologické celky)

`units.json` je zdroj pre `App\Service\Geo\MountainRangeLocator`. Podľa neho sa určuje pohorie hríbika
na detaile („Pohorie: Nízke Tatry“). Hľadanie prebieha lokálne, bez volania externej služby.

- **Zdroj:** OpenStreetMap, relácie `boundary=geomorphological-unit` + `natural=mountain_range`
  na Slovensku a v Česku. Licencia ODbL, na detaile je preto odkaz „© OpenStreetMap“.
- **Pokrytie:**
  - **Slovensko:** takmer celé (~280 jednotiek). Chýbajú najmä kotliny (Liptovská, Podtatranská…)
    a niektoré celky (Muránska planina).
  - **Česko:** v OSM sú len karpatské pohoria pri hranici (Bílé Karpaty, Javorníky, Beskydy…).
- **Úrovne:** vyššie úrovne (Karpaty, provincie, subprovincie, oblasti ako Slovenské rudohorie)
  sú vyradené zoznamom v `higher.py`. Zo zvyšku sa vyberá najväčšia jednotka, ktorá bod obsahuje,
  teda celok, nie podcelok.
- **Geometria:** zjednodušená na ~50 m (`simplify(0.0005)`) a jednotky sú zoradené od najväčšej.

## Ako súbor vytvoriť nanovo

Na vytvorenie treba Python so shapely a pyproj. Aplikácia ich nepotrebuje.

```bash
python3 -m venv venv && ./venv/bin/pip install shapely pyproj

# 1) Stiahnuť jednotky s geometriou (verejný Overpass býva preťažený — opakovať, prípadne iný server)
for cc in SK CZ; do
  curl -s -A "Hribiky.sk/1.0 (+https://hribiky.sk)" --data-urlencode \
    "data=[out:json][timeout:300];area[\"ISO3166-1\"=\"$cc\"][admin_level=2]->.c;rel(area.c)[natural=mountain_range];out geom;" \
    -o $cc.json https://maps.mail.ru/osm/tools/overpass/api/interpreter
done

# 2) Poskladať polygóny (vypíše aj hierarchiu na kontrolu) a exportovať
./venv/bin/python build.py SK.json CZ.json units-full.json > tree.txt
./venv/bin/python export.py units-full.json units.json .
```

Potom na serveri spusti `php bin/console app:resolve-locations --all`. Pohoria sa tak prepočítajú
všetkým hríbikom. Obec a okres sa pritom znova stiahnu z Nominatimu, čo trvá asi 1 s na hríbik.
