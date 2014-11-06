#!/bin/bash
# Random password generator
# symbols, length
function ssh_random_string {
  symbols='A-Z-a-z-0-9'
  if [[ ! -z $1 ]]; then
    symbols=$1
  fi

  length=40
  if [[ $2 =~ ^[0-9]+$ ]]; then
    length=$2
  fi

  < /dev/urandom tr -dc ${symbols} | head -c${length} && echo
}

