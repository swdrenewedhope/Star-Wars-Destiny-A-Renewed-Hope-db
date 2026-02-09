This folder stores database-related data in a format that can be easily updated and reviewed by multiple people.

## Validating

Using python >=2.6, type in command line:

```
./validate.py --verbose --fix_formatting
```

The above script requires python package `jsonschema` which can be installed via `pip install -U jsonschema`.

## Description of Properties in Schemas

Required properties are in **bold**.

### Formatting

**Bold** ``<b>`` for:
  - The word **Action** and **Power Action**
  - Card subtypes e.g. **trooper** and **creature**

*Italic* ``<em>`` for reminder text e.g. *(paying its cost)*

Card types (e.g. character), faction (e.g. Blue), and affiliation (e.g. hero) are not formatted differently (though faction needs a capital letter).

### Set Schema

* **code** - The acronym of the set name, with matching case. Example: `"AW"` for Awakenings.
* **cycle_code** - The cycle that the set belongs too, though this is legacy it needs to be set. Will be deprecated moving forward.
* **name** - Properly formatted name of the set. Examples: `"Awakenings"`.
* `project_name` - Name of the project the set belongs too. Example: A Renewed Hope.
* **cgdb_id_start** - The purpose of this is not yet known. Set to null.
* **cgdb_id_end** - The purpose of this is not yet known. Set to null.
* **position** - Number of the set. Examples: `1` for Awakenings.
* **date_released** - Date when the set is released. Format of the date is YYYY-MM-DD. May be `null` - will show up as unreleased.
* **size** - Number of different cards in the set. May be `null`. Examples: `174` for Awakenings, `null` for assorted draft cards.

### Card Schema

- **set_code** (string)  
  Acronym of set code. Example: `"AW"` for Awakenings.

- **type_code** (string)  
  Type of card. Possible values: `"battlefield"`, `"character"`, `"event"`, `"support"`, `"upgrade"`, `"plot"`, `"downgrade"`.

- **faction_code** (string)  
  Faction (or colour) of the card. Possible values: `"blue"`, `"gray"`, `"red"`, `"yellow"`.

- **affiliation_code** (string)  
  Affiliation / Alignment. Possible values: `"hero"`, `"neutral"`, `"villain"`.

- **rarity_code** (string)  
  Initial of rarity: (S)tarter, (C)ommon, (U)ncommon, (R)are or (L)egendary.  
  Note: ARH cards are entered with rarity `S`.

- `reprint_of` (int)  
  Takes the **code** of an existing card. Other details are still expected to be filled out.

- `parallel_die` (int)  
  Takes the **code** of an existing card.

- **position** (int)  
  Position of the card in the set / card number.

- **code** (int)  
  5 digit card identifier. First two digits are the set position, last three are position of the card within the set (printed on the card).

- **ttscardid** (int)  
  **Deprecated** — Put `00000` until it is removed completely.

- **name** (string)  
  Name of the card.

- `subtitle` (string)  
  Subtitle of (usually) a character card. Example: Captain Phasma → Elite Trooper.

- `cost` (string \| null)  
  Play cost of the card. Use `null` when the card has a variable cost (i.e. `X` values).

- `health` (int)  
  The health value of the card. Example: Captain Phasma (Elite Trooper) has 11 health.

- `points` (string)  
  The points cost of the card. Example: Captain Phasma (Elite Trooper) has 12/15.

- `text` (string)  
  The text of the card. Example: `"Your non-unique characters have the Guardian keyword"`.

- **deck_limit** (int)  
  The amount of copies of the card that is legal to include.

- `flavor` (string)  
  Flavor text. Example: `"Whatever you're planning, it won't work"`.

- `illustrator` (string)  
  Artist who created the art for the card.

- **is_unique** (bool)  
  Is the card unique? `true`/`false`.

- **has_die** (bool)  
  Does the card itself have a die panel? `true`/`false`.

- `has_errata` (bool)  
  Was the printed text of the card legally changed? `true`/`false`.

- `flip_card`  
  Unknown, do not use yet.

- `sides` (string)  
  If the card has a die, this represents the die faces. It is an array of six elements.

  <details>
  <summary>Valid Symbols</summary>

  - `-` — Blank
  - `Dc` — Discard
  - `Dr` — Disrupt
  - `F` — Focus
  - `MD` — Melee Damage
  - `R` — Resource
  - `RD` — Ranged Damage
  - `Sh` — Shield
  - `Sp` — Special
  - `ID` — Indirect Damage
  - `Fr` — Feral
  - `Re` — Reroll
  - `X` — Variable Cost

  </details>

  Notes (sides):  
  - A plus (`+`) sign can be used for sides that are modified (blue) values. Example: `+2MD`  
  - A die with a cost should have the cost after the entire side is written out. Example: `+2MD1`

- `subtypes` (string | array)
  The subtypes of a card. Example: Captain Phasma (Elite Trooper) has the subtypes: Trooper. Leader.

  <details>
  <summary>Valid Subtypes</summary>

  - ability
  - advisor
  - apprentice
  - artillery
  - bomb
  - bounty
  - bounty-hunter
  - creature
  - cultist
  - curse
  - death-star
  - droid
  - engineer
  - equipment
  - ewok
  - form
  - format
  - guard
  - gungan
  - injury
  - inquisitor
  - intel
  - jawa
  - jedi
  - leader
  - location
  - mission
  - mod
  - move
  - nightbrother
  - partisan
  - pilot
  - pirate
  - podracer
  - scavenger
  - scoundrel
  - shapeshifter
  - sith
  - spectre
  - spell
  - spy
  - title
  - trap
  - trooper
  - vehicle
  - weapon
  - witch
  - wookie
  - musician
  - capital-ship
  - twilek

  </details>

### JSON text editing tips

Full description of (very simple) JSON format can be found [here](http://www.json.org/), below there are a few tips most relevant to editing this repository.

### Non-ASCII symbols

When symbols outside the regular [ASCII range](https://en.wikipedia.org/wiki/ASCII#ASCII_printable_code_chart) are needed, UTF-8 symbols come in play. These need to be escaped using `\u<4 letter hexcode>`.

To get the 4-letter hexcode of a UTF-8 symbol (or look up what a particular hexcode represents), you can use a UTF-8 converter, such as [this online tool](http://www.ltg.ed.ac.uk/~richard/utf-8.cgi).

#### Quotes and Multiple Lines

To have text spanning multiple lines, use `\n` to separate them. To have quotes as part of the text, use `\"`.  For example, `"\"Orange and white: one of a kind.\" <cite>Poe Dameron</cite>"` results in following flavor text:

> *"Orange and white: one of a kind." Poe Dameron*

#### Star Wars Destiny symbols

These can be used in a card's `text` section.

 * `[melee]`
 * `[ranged]`
 * `[focus]`
 * `[discard]`
 * `[disrupt]`
 * `[shield]`
 * `[resource]`
 * `[special]`
 * `[blank]`

### Translations

To merge new changes in default language in all locales, run the CoffeeScript script `update_locales`.

Pre-requisites:
 * `node` and `npm` installed
 * `npm -g install coffee-script`

Usage: `coffee update_locales.coffee [language code]`

(NOTE: a folder with the language code must exists in `translations` folder)
