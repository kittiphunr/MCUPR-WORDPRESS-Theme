#!/usr/bin/env python3
"""Builds mcupr/inc/feather-icons.php from the Feather icon set (MIT, Cole Bemis): the inner markup of every icon.
   npm install feather-icons@4.29.2 in a scratch folder, then:  python3 scripts/make-feather.py /path/to/node_modules/feather-icons/dist/icons"""
import re, sys, glob, os
src = sys.argv[1]
out = ["<?php", "/** The Feather icon set (https://feathericons.com, MIT licence – see feather-LICENSE.txt): name => inner SVG markup. Made by scripts/make-feather.py, do not edit. */", "defined( 'ABSPATH' ) || exit;", "return array("]
for f in sorted(glob.glob(os.path.join(src, "*.svg"))):
    n = os.path.basename(f)[:-4]
    s = open(f, encoding="utf-8").read()
    inner = re.search(r"<svg[^>]*>(.*)</svg>", s, re.S).group(1).strip()
    inner = re.sub(r"></(?:path|line|circle|rect|polyline|polygon|ellipse)>", "/>", inner)
    out.append("\t'%s' => '%s'," % (n, inner.replace("\\", "\\\\").replace("'", "\\'")))
out.append(");")
open(os.path.join(os.path.dirname(__file__), "..", "mcupr", "inc", "feather-icons.php"), "w", encoding="utf-8").write("\n".join(out) + "\n")
print(len(out) - 5, "icons")
