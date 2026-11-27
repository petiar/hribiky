"""Export geomorfologických celkov (bez vyšších úrovní) do kompaktného JSON pre PHP."""
import json, sys
from shapely import wkt
from shapely.geometry import mapping

sys.path.insert(0, sys.argv[3])
from higher import HIGHER  # noqa

units = json.load(open(sys.argv[1])); seen = set(); out = []
for u in units:
    if u['id'] in seen: continue
    seen.add(u['id'])
    if {u['name'], u['sk'], u['cs']} & HIGHER: continue
    g = wkt.loads(u['wkt']).simplify(0.0005, preserve_topology=True)
    polys = [g] if g.geom_type == 'Polygon' else list(g.geoms)
    rings = [[[[round(x, 5), round(y, 5)] for x, y in ring.coords] for ring in [p.exterior, *p.interiors]] for p in polys]
    minx, miny, maxx, maxy = g.bounds
    out.append({'osm': u['id'], 'sk': u['sk'] or u['name'], 'cs': u['cs'] or u['sk'] or u['name'],
                'km2': round(u['km2']), 'bbox': [round(minx, 4), round(miny, 4), round(maxx, 4), round(maxy, 4)], 'polygons': rings})
out.sort(key=lambda u: -u['km2'])
json.dump({'source': 'OpenStreetMap (ODbL), boundary=geomorphological-unit, natural=mountain_range', 'units': out},
          open(sys.argv[2], 'w'), ensure_ascii=False, separators=(',', ':'))
print(len(out), 'jednotiek')
