---
description: Radio de impacto — qué partes de htdocs son producción intocable
paths:
  - "_fuente/**"
  - "api/**"
  - "admin/**"
  - "cron/**"
  - "supertecnicas/**"
  - "config/**"
  - "*.html"
  - "datos_oficiales.json"
---

# Estás fuera del subproyecto `dashboard/`

Este árbol es la **web pública en producción** de superligafrontier.es. El
trabajo autorizado vive entero dentro de `dashboard/`.

**Antes de editar cualquier fichero de este ámbito: para y avisa.** No es una
recomendación de estilo; un cambio aquí sale publicado.

## Las dos únicas excepciones

| Fichero | Cambio permitido | Cuándo |
|---|---|---|
| `.gitignore` | **añadir** las 6 líneas de `dashboard/data/` al final | §10 Bootstrap |
| `config/secrets.example.php` | **añadir** `admin_dashboard_user` y `admin_dashboard_pass_hash` | paso 04 |

En ambos casos es *append*: no se reordena, no se reescribe, no se borra nada.

## Lectura sí, escritura no

- `datos_oficiales.json` se **lee** (paso 11, importar equipos de la web) y
  **nunca** se escribe. Es el fichero del que vive la web pública.
- `_fuente/styles.css` se **enlaza** desde las pantallas de `dashboard/` y
  nunca se edita. Lo que falte se define en `dashboard/css/dashboard.css`.
- `config/admin_auth.php` se **usa** (`requerirAdminBasicAuthConClaves`) y
  nunca se modifica.
- `supertecnicas/` es el **precedente que se copia**, no un fichero que se
  edita: mismas convenciones, prefijo `pl` en vez de `st`.
