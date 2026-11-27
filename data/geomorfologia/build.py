"""Poskladá polygóny geomorfologických jednotiek z Overpass `out geom` a vypíše hierarchiu."""
import json, sys
from shapely.geometry import LineString, Polygon, MultiPolygon
from shapely.ops import polygonize, unary_union, transform
import pyproj  # noqa

def build(rel):
    lines = [LineString([(p['lon'], p['lat']) for p in m['geometry']])
             for m in rel.get('members', []) if m['type'] == 'way' and m.get('geometry') and m.get('role') in ('outer', '')]
    polys = list(polygonize(unary_union(lines)))
    if not polys:
        return None
    g = unary_union(polys)
    return g

def area_km2(g):
    from shapely.ops import transform
    import pyproj
    proj = pyproj.Transformer.from_crs('EPSG:4326', 'EPSG:3035', always_xy=True).transform
    return transform(proj, g).area / 1e6

out = []
for path in sys.argv[1:-1]:
    for e in json.load(open(path))['elements']:
        g = build(e)
        if g is None or g.is_empty:
            print('BEZ GEOMETRIE', e['tags'].get('name'), file=sys.stderr); continue
        t = e['tags']
        out.append({'id': e['id'], 'name': t.get('name'), 'sk': t.get('name:sk'), 'cs': t.get('name:cs'),
                    'wikidata': t.get('wikidata'), 'g': g, 'km2': area_km2(g)})

# rodič = najmenšia jednotka, ktorá obsahuje väčšinu plochy
by_size = sorted(out, key=lambda u: u['km2'])
for u in out:
    rp = u['g'].representative_point()
    cands = [p for p in by_size if p is not u and p['km2'] > u['km2'] * 1.05 and p['g'].buffer(0).intersection(u['g'].buffer(0)).area > 0.8 * u['g'].area]
    u['parent'] = cands[0]['id'] if cands else None
ids = {u['id']: u for u in out}
for u in out:
    d, p = 0, u['parent']
    while p: d += 1; p = ids[p]['parent']
    u['depth'] = d
for u in sorted(out, key=lambda u: (u['depth'], -u['km2'])):
    par = ids[u['parent']]['name'] if u['parent'] else ''
    print(u['depth'], int(u['km2']), u['sk'] or u['name'], '<', par, sep='\t')
json.dump([{k: v for k, v in u.items() if k != 'g'} | {'wkt': u['g'].simplify(0.0005).wkt} for u in out], open(sys.argv[-1], 'w'))
