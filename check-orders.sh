#!/bin/sh

# Debug ouput
#set -x

GAME="$1"
WARNINGS=0

if [ -z "$ERESSEA" ] ; then
  ERESSEA="$HOME/eressea"
fi
PYTHON_HOME="$ERESSEA/server/bin"
DBTOOL_HOME="$ERESSEA/orders-php"
GAME_HOME="$ERESSEA/game-$GAME"
TEXTDOMAIN="orders"
TEXTDOMAINDIR="$DBTOOL_HOME/locale"

export TEXTDOMAINDIR

GETTEXT() {
  gettext "$TEXTDOMAIN" "$*"
}

checkpass() {
  FACTION="$1"
  PASSWORD="$2"
  if [ -n "$PASSWORD" ]
  then
    if "$PYTHON_HOME/checkpasswd.py" "$GAME_HOME/eressea.db" "$FACTION" "$PASSWORD"
    then
      return 0
    fi
  fi
  # shellcheck disable=SC2059
  printf "$(GETTEXT 'WARNING: Unknown faction %s or invalid password!')\\n" "$FACTION"
  WARNINGS=1
  return 1
}

check() {
  LANGUAGE="$1"
  FILENAME="$2"
  "echeck" -w1 -x -R "e$GAME" -L "$LANGUAGE" "$FILENAME"
}

orders() {
  php "$DBTOOL_HOME/cli.php" "$@"
}

OUTPUT=$(mktemp /tmp/check.XXXXX)
[ -d "$ERESSEA/game-$GAME/orders.dir" ] || exit
cd "$ERESSEA/game-$GAME/orders.dir" || exit
orders -d orders.db select | while read -r LANGUAGE EMAIL FILENAME ; do
  export LANGUAGE
  SUBJECT="$(GETTEXT 'orders received')"
  found=0
  mkfifo check.pipe
  orders info "$FILENAME" > check.pipe &
  while read -r FACTION PASSWORD ; do
    checkpass "$FACTION" "$PASSWORD" >> "$OUTPUT" 2>&1
    found=1
  done < check.pipe
  rm -f check.pipe
  if [ $found -ne 0 ]; then
    check "$LANGUAGE" "$FILENAME" >> "$OUTPUT" 2>&1
  else
    WARNINGS=1
    printf "$(GETTEXT 'WARNING: Unknown faction or invalid password in %s!')\\n" "$FILENAME" >> "$OUTPUT" 
  fi
  orders update "$FILENAME" 2
  if [ $WARNINGS -gt 0 ] ; then
    SUBJECT="$(GETTEXT 'orders received (warning)')"
  fi
  mutt -s "[E$GAME] $SUBJECT" "$EMAIL" < "$OUTPUT"
done
rm -f "$OUTPUT"
