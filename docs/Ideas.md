What should work in first version of the project and what functionality could be added later?

My ideas for the moment:

Main part of the project is the 
  1. development / implementation of the **new datatype for math formulae**

Secondary it should be possible to 
  2. add math formulae manually and 
  3. identify simple formulae in wikipedia to represent it in wikidata (with our datatype)

Maybe later on there could be a function to
  4. represent math formula in Wikipedia as reference to wikidata source
  5. make **calculations** (need extra user interface / API), where a user can call a function with the formula to use, for example: **calculate('Q35875', 'P2345', identifier, 'Q11423=10 Q11570)** with
    * Q35875 = item 'mass–energy equivalence'
    * P2345 = fictitious property 'mathematical formula' or 'physical fomula'
    * identifier = if there where more forumlae in this item we have to identify the one we want to calculate with
    * 'Q11423=10 Q11570' is our input-String with Q11423 = item 'mass' with unit Q11570 = kilogram -> 10 kg
  6. find possible results for a given input (so we can say that we got mass and acceleration and wikidata tells us that we can calculate the force (as item Q123 'basic equation of mechanics')
