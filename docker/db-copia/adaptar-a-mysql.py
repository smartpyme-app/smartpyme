#!/usr/bin/env python3
"""Adapta el dump de MariaDB (producción) para importarlo en MySQL 8/9 (p. ej. con Workbench).

Uso:  python3 docker/db-copia/adaptar-a-mysql.py docker/mariadb/dumps/smartpyme_prod.sql
Escribe <archivo>.mysql.sql al lado; el original no se toca.

Qué cambia (solo el texto del dump, nunca datos):
  1. Columnas generadas (GENERATED ALWAYS AS ...): MariaDB vuelca su valor en los INSERT y MySQL
     lo rechaza (ERROR 3105). Se reescribe el INSERT con lista explícita de columnas sin la
     generada; MySQL la recalcula sola.
  2. FK con ON DELETE/UPDATE CASCADE o SET NULL sobre una columna de la que depende una
     columna generada STORED: MySQL la rechaza (ERROR 1215). Se quita esa acción (solo local).
  3. DEFINER de vistas/rutinas: los usuarios de producción no existen en local; se quita para
     que el dueño sea quien importa.
No hace falta corregir nada en producción: MariaDB acepta su propio dump.
"""
import re
import sys

src = sys.argv[1]
dst = src[:-4] + ".mysql.sql" if src.endswith(".sql") else src + ".mysql.sql"

re_create = re.compile(r"^CREATE TABLE `([^`]+)`")
re_col = re.compile(r"^\s+`([^`]+)` ")
re_insert = re.compile(r"^INSERT INTO `([^`]+)` VALUES")
re_definer = re.compile(r"/\*!50013 DEFINER=`[^`]*`@`[^`]*` SQL SECURITY DEFINER \*/")
re_definer2 = re.compile(r"DEFINER=`[^`]*`@`[^`]*`\s*")
re_fk_accion = re.compile(r" ON (DELETE|UPDATE) (CASCADE|SET NULL|SET DEFAULT)")

tablas = {}          # tabla -> (columnas, índices de columnas generadas)
tabla_actual = None
cols = []
gen = []
en_insert = None     # tabla cuyo INSERT multi-fila estamos reescribiendo
cambios = {"insert": 0, "filas": 0, "definer": 0, "fk": 0}


def quitar_generadas(fila, idx_gen, ncols):
    """fila = "(v1,v2,...)" sin el separador final. Parsea respetando comillas."""
    vals, buf, q, esc = [], [], False, False
    for ch in fila[1:-1]:
        if q:
            buf.append(ch)
            if esc:
                esc = False
            elif ch == "\\":
                esc = True
            elif ch == "'":
                q = False
        elif ch == "'":
            q = True
            buf.append(ch)
        elif ch == ",":
            vals.append("".join(buf)); buf = []
        else:
            buf.append(ch)
    vals.append("".join(buf))
    if len(vals) != ncols:
        raise SystemExit(f"Fila con {len(vals)} valores, se esperaban {ncols}: {fila[:200]}")
    return "(" + ",".join(v for i, v in enumerate(vals) if i not in idx_gen) + ")"


with open(src, "r", encoding="utf-8", errors="surrogateescape", newline="") as f, \
        open(dst, "w", encoding="utf-8", errors="surrogateescape", newline="") as out:
    for linea in f:
        if en_insert:
            cuerpo = linea.rstrip("\r\n")
            fin = cuerpo[-1]  # ',' o ';'
            columnas, idx_gen = tablas[en_insert]
            out.write(quitar_generadas(cuerpo[:-1], idx_gen, len(columnas)) + fin + "\n")
            cambios["filas"] += 1
            if fin == ";":
                en_insert = None
            continue

        m = re_create.match(linea)
        if m:
            tabla_actual, cols, gen, base_gen = m.group(1), [], [], set()
        elif tabla_actual:
            mc = re_col.match(linea)
            if mc and not linea.lstrip().startswith(("PRIMARY", "UNIQUE", "KEY", "CONSTRAINT", "FULLTEXT", "INDEX")):
                if " GENERATED ALWAYS AS " in linea:
                    gen.append(len(cols))
                    expr = linea.split(" GENERATED ALWAYS AS ", 1)[1]
                    base_gen.update(re.findall(r"`([^`]+)`", expr))
                cols.append(mc.group(1))
            elif base_gen and linea.lstrip().startswith("CONSTRAINT") and " FOREIGN KEY " in linea:
                # MySQL (no MariaDB) prohíbe CASCADE/SET NULL en una FK sobre una columna de la que
                # depende una columna generada STORED (ERROR 1215). Se quita solo esa acción.
                fk_cols = set(re.findall(r"`([^`]+)`", linea.split("FOREIGN KEY", 1)[1].split("REFERENCES", 1)[0]))
                if fk_cols & base_gen:
                    nueva = re_fk_accion.sub("", linea)
                    if nueva != linea:
                        cambios["fk"] += 1
                        linea = nueva
            elif linea.startswith(")"):
                if gen:
                    tablas[tabla_actual] = (cols, set(gen))
                tabla_actual = None

        mi = re_insert.match(linea)
        if mi and mi.group(1) in tablas:
            t = mi.group(1)
            columnas, idx_gen = tablas[t]
            lista = ",".join(f"`{c}`" for i, c in enumerate(columnas) if i not in idx_gen)
            resto = linea[mi.end():].strip()
            out.write(f"INSERT INTO `{t}` ({lista}) VALUES\n")
            cambios["insert"] += 1
            if resto:  # todas las filas en la misma línea (dump sin --extended-insert por líneas)
                raise SystemExit(f"INSERT de {t} en una sola línea: formato no soportado")
            en_insert = t
            continue

        if "DEFINER=" in linea:
            nueva = re_definer.sub("", linea)
            if nueva == linea:
                nueva = re_definer2.sub("", linea)
            if nueva != linea:
                cambios["definer"] += 1
                linea = nueva
        out.write(linea)

print(f"Listo: {dst}")
print(f"Tablas con columnas generadas: {', '.join(tablas) or 'ninguna'}")
print(f"INSERT reescritos: {cambios['insert']} ({cambios['filas']} filas) | DEFINER quitados: {cambios['definer']} | FK sin CASCADE: {cambios['fk']}")
