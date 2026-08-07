#!/usr/bin/env bash
#
# Lancia tutte le suite di console e area clienti, una per processo.
#
# Il processo separato non è pignoleria: WordPress tiene traccia degli script e
# dei fogli di stile già stampati, quindi due sezioni rese nella stessa
# richiesta fanno risultare "mancanti" asset che erano soltanto già stati
# emessi. Ci siamo cascati, e i test dicevano il falso.
#
# Uso:
#   tests/console/run.sh                      # cerca `wp` nel PATH
#   WP="php wp-cli.phar" tests/console/run.sh # oppure si indica come invocarlo
#
# Variabili:
#   WP        comando di WP-CLI (default: "wp")
#   WP_PATH   radice di WordPress (default: quella dedotta da WP-CLI)

set -uo pipefail

WP="${WP:-wp}"
QUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -n "${WP_PATH:-}" ]; then
	WP="$WP --path=$WP_PATH"
fi

falliti=0
totale=0

for suite in "$QUI"/*.php; do
	nome="$(basename "$suite")"
	# helpers.php è la cassetta degli attrezzi, rendi.php uno strumento di
	# servizio: nessuno dei due contiene asserzioni.
	case "$nome" in
		helpers.php | rendi.php) continue ;;
	esac

	printf '\n\033[1m── %s\033[0m\n' "$nome"
	totale=$((totale + 1))

	if ! $WP eval-file "$suite"; then
		falliti=$((falliti + 1))
	fi
done

printf '\n────────────────────────────────────────\n'
if [ "$falliti" -eq 0 ]; then
	printf '\033[32mTutte le %d suite sono passate.\033[0m\n' "$totale"
	exit 0
fi

printf '\033[31m%d suite su %d hanno fallito.\033[0m\n' "$falliti" "$totale"
exit 1
