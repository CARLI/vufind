package org.vufind.index;

import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;
import org.marc4j.marc.*;
import org.solrmarc.tools.Utils;
import java.util.*;
import org.solrmarc.index.SolrIndexer;

//import org.apache.log4j.Logger;
//protected static Logger logger = Logger.getLogger(Utils.class.getName());

public class carli_format {

   /////////////////////////////////
   interface Comparitor {
      public boolean compare(String item, String pattern);
   }

   public static class BeginsWithComparitor implements Comparitor {
      public boolean compare(String item, String pattern) {
          if (item.trim().toLowerCase(Locale.US).startsWith(pattern.toLowerCase(Locale.US))) {
             return true;
          }
          return false;
      }
   };

   public static class EqualsComparitor implements Comparitor {
      public boolean compare(String item, String pattern) {
          if (item.trim().equalsIgnoreCase(pattern)) {
             return true;
          }
          return false;
      }
   };

   public static class ContainsComparitor implements Comparitor {
      public boolean compare(String item, String pattern) {
        String itm = item.trim().toLowerCase(Locale.US);
        String pat = pattern.toLowerCase(Locale.US);
        String rep = itm.replaceFirst(pat, "###match###");
        if (!rep.equals(itm)) {
                return true;
        }
        return false;
      }
   };

   public static class TokenizedContainsComparitor extends ContainsComparitor {
      public boolean compare(String item, String pattern) {
         String[] tokens = item.split("\\s+");
         for (String token : tokens) {
            if (super.compare(token, pattern)) return true;
         }
         return false;
      }
   };


public static TokenizedContainsComparitor tokenizedContainsComparitor = new TokenizedContainsComparitor();
public static ContainsComparitor containsComparitor = new ContainsComparitor();
public static EqualsComparitor equalsComparitor = new EqualsComparitor();
public static BeginsWithComparitor beginsWithComparitor = new BeginsWithComparitor();

    // a safe way to get LC value (returns empty string instead of null when not found)
    public String getFirstFieldValLowerCase(Record record, String pattern) {
        String ret = SolrIndexer.instance().getFirstFieldVal(record, pattern);
        if (ret != null) {
            ret = ret.toLowerCase(Locale.US);
        } else {
            ret = "";
        }
        return ret;
    }

    public Set getCarliFormat(Record record)
    {
        Set result = new LinkedHashSet();

        String LDR_06 = getFirstFieldValLowerCase(record, "000[6]");
        String recType = LDR_06; // synonym

        String LDR_07 = getFirstFieldValLowerCase(record, "000[7]");
        String bibLvl = LDR_07; // synonym

        if(recType.equals("a")){
           if(bibLvl.equals("a")){
              result.add("Book");
           } else if (bibLvl.equals("b")) {
              result.add("Journal / Magazine");
           } else if (bibLvl.equals("c")) {
              result.add("Archival Collection");
           } else if (bibLvl.equals("d")) {
              result.add("Archive");
           } else if (bibLvl.equals("i")) {
              result.add("Textual Material");
           } else if (bibLvl.equals("m")) {
              result.add("Book");
           } else if (bibLvl.equals("s")) {
              result.add("Journal / Magazine");
           }
        } else if (recType.equals("b")) {
           result.add("Archive");
        } else if (recType.equals("c")) {
           result.add("Music Score");
        } else if (recType.equals("d")) {
           result.add("Music Manuscript");
        } else if (recType.equals("e")) {
           result.add("Map");
        } else if (recType.equals("f")) {
           result.add("Manuscript Map");
        } else if (recType.equals("g")) {
           result.add("Movie");
        } else if (recType.equals("i")) {
           result.add("Spoken Word Recording");
        } else if (recType.equals("j")) {
           result.add("Music Recording");
        } else if (recType.equals("k")) {
           result.add("2D Art");
        } else if (recType.equals("m")) {
           result.add("Software / Computer File");
        } else if (recType.equals("o")) {
           result.add("Kit");
        } else if (recType.equals("p")) {
           result.add("Mixed Material");
        } else if (recType.equals("r")) {
           result.add("3D Object");
        } else if (recType.equals("t")) {
           result.add("Manuscript");
        }

        String _007_00 = getFirstFieldValLowerCase(record, "007[0]");
        String _007_01 = getFirstFieldValLowerCase(record, "007[1]");
        String medium = _007_00 + _007_01;

        // NOTE:
        // Since set_medium2 is composed from the 338$a
        // (controlled vocabulary); and the 300$a, 305$a, 538$a and
        // 690$a (all free text), I guess we need to perform more
        // forgiving substring matching, instead of stricter complete
        // (or mostly complete) string matching. Therefore we will
        // use setItemContains() to match on set_medium2 below.
        Set set_medium2 = new LinkedHashSet();
        addSubfieldDataToSet(record, set_medium2, "300", "a");
        addSubfieldDataToSet(record, set_medium2, "300", "b");
        addSubfieldDataToSet(record, set_medium2, "300", "e");
        addSubfieldDataToSet(record, set_medium2, "305", "a");
        addSubfieldDataToSet(record, set_medium2, "305", "b");
        addSubfieldDataToSet(record, set_medium2, "305", "c");
        addSubfieldDataToSet(record, set_medium2, "338", "a");
        addSubfieldDataToSet(record, set_medium2, "344", "c");
        addSubfieldDataToSet(record, set_medium2, "538", "a");
        addSubfieldDataToSet(record, set_medium2, "690", "a");

        Set set_medium3 = new LinkedHashSet();
        addSubfieldDataToSet(record, set_medium3, "300", "a");
        addSubfieldDataToSet(record, set_medium3, "300", "b");
        addSubfieldDataToSet(record, set_medium3, "300", "e");
        addSubfieldDataToSet(record, set_medium3, "305", "a");
        addSubfieldDataToSet(record, set_medium3, "305", "b");
        addSubfieldDataToSet(record, set_medium3, "305", "c");
        addSubfieldDataToSet(record, set_medium3, "338", "a");
        addSubfieldDataToSet(record, set_medium3, "344", "c");
        addSubfieldDataToSet(record, set_medium3, "347", "b");
        addSubfieldDataToSet(record, set_medium3, "538", "a");
        addSubfieldDataToSet(record, set_medium3, "690", "a");

        Set set_gmd = new LinkedHashSet();
        addSubfieldDataToSet(record, set_gmd, "245", "h");

        Set carrierTerm = new LinkedHashSet();
        addSubfieldDataToSet(record, carrierTerm, "338", "a");

        Set carrierCode = new LinkedHashSet();
        addSubfieldDataToSet(record, carrierCode, "338", "b");

        Set set_300c = new LinkedHashSet();
        addSubfieldDataToSet(record, set_300c, "300", "c");

        if (setItemContains(set_gmd, "electronic resource") ||
            setItemEquals(carrierTerm, "online resource")||
            setItemBeginsWith(carrierTerm, "computer") ||
            setItemBeginsWith(carrierCode, "c")) {
           result.add("Electronic");
        }

        if (setItemContains(set_gmd, "microform") ||
            setItemBeginsWith(carrierTerm, "microf") ||
            setItemBeginsWith(carrierTerm, "microo") ||
            setItemBeginsWith(carrierTerm, "aperture") ||
            setItemBeginsWith(carrierCode, "h") ||
            (_007_00.equals("h"))
            ) {
           result.add("Microform");
        }

        // Blu-ray:
        // ((Medium=vd OR GMD=video*) AND (Medium3=bluray OR Medium3="blu ray" OR Medium3="blu-ray"))
        // OR
        // (RecType=g AND Medium=v* AND (Medium3=bluray OR Medium3="blu ray" OR Medium3="blu-ray"))
        if (
              ( ( itemEquals(medium, "vd") || setItemContains(set_gmd, "video") ) && ( setItemContains(set_medium3, "bluray") || setItemContains(set_medium3, "blu ray") || setItemContains(set_medium3, "blu-ray")) )
              ||
              ( itemEquals(recType, "g") && itemBeginsWith(medium, "v") && ( setItemContains(set_medium3, "bluray") || setItemContains(set_medium3, "blu ray") || setItemContains(set_medium3, "blu-ray")) )
        ) {
           result.add("Blu-ray");
        }

        // DVD:
        // ((Medium=vd OR GMD=video*) AND Medium3=dvd*)
        //  OR
        // (RecType=g AND Medium=v* AND Medium3=dvd*)
        if (
             ((itemEquals(medium, "vd") || setItemContains(set_gmd, "video")) && setItemContains(set_medium3, "dvd"))
             ||
             (itemEquals(recType, "g") && itemBeginsWith(medium, "v") && setItemContains(set_medium3, "dvd"))
        ) {
           result.add("DVD");
        }

        // VHS:
        // (Medium=vf OR GMD=video* OR RecType=g) AND (Medium2=vhs OR Medium2=videocassette)
        if (
              (itemEquals(medium, "vf") || setItemContains(set_gmd, "video") || itemEquals(recType, "g")) && (setItemContains(set_medium2, "vhs") || setItemContains(set_medium2, "videocassette"))
        ) {
           result.add("VHS");
        }

        // Slides:
        // RecType=g AND (GMD="slide" OR Medium=gs OR Medium=gt)
        if (
              itemEquals(recType, "g") && ( setItemContains(set_gmd, "slide") || itemEquals(medium, "gs") ||  itemEquals(medium, "gt")  )
        ) {
           result.add("Slide");
        }

        // Film:
        // (RecType=g AND (GMD="motion picture" or Medium=m*)) OR Medium2=”film reel”
        if (
              ( ( itemEquals(recType, "g") && ( setItemContains(set_gmd, "motion picture") || itemBeginsWith(medium, "m") ) ) || setItemContainsTokenized(set_medium2, "film reel")  )
        ) {
           result.add("Reel-to-Reel");
        }

        /********
        Audio CD:

        (RecType="j" <musical sound recording> OR RecType="i" <non-musical sound recording> OR 245h="sound" OR 007ab="sd" <Sound recording, sound disc)
 
        <Stanza 1 pulls anything that is a sound recording. This is necessary to establish before the other conditions can be met, especially the 007/byte 06="g">
 
        AND any of the following options 1-3 are present:
 
        option 1:

        <finds anything with 4 3/4 in. or 12 cm. diameter in 007>
        007 byte 06=“g”
 
        OR
 
        option 2:
        <finds anything with 4 3/4 in. or 12 cm. diameter in 300c>
        300c=“4 ¾ in” OR "12 cm"
 
        OR
 
        option 3:
        <finds anything with "compact disc" OR "CD" OR "audio disc" listed in the specified fields; this has a nested AND statement only specific to option 3>
 
        (the term "compact disc" OR "CD" OR "audio disc" appears in the 300a, 300b, 300e, 305a, 305b, 305c, 338a, 347b, 538a, or 690a)
        AND
        (the term "33 1/3" OR "33 ⅓" OR "33⅓" does NOT appear in the 300a, 300b, 300e, 305a, 305b, 305c, 338a, 538a, or 690a)
 
        ********/
        if ( itemEquals(recType, "j") || itemEquals(recType, "i") || setItemContains(set_gmd, "sound") || itemEquals(medium, "sd") ) {

            String _007_06 = getFirstFieldValLowerCase(record, "007[6]");

            if (itemEquals(_007_06, "g")) {

                result.add("Audio CD");

            } else if (
                    (setItemContains(set_medium3, "compact disc") || setItemContainsTokenized(set_medium3, "CD") || setItemContains(set_medium3, "audio disc") )
                    &&
                    !( setItemContains(set_medium2, "33 1/3") || setItemContains(set_medium2, "33 ⅓") || setItemContains(set_medium2, "33⅓") ||  setItemContains(set_medium2, "33 ¹/₃") || setItemContains(set_medium2, "33¹/₃"))
            ) {

                result.add("Audio CD");

            } else {

                if (
                    setItemContains(set_300c, "12 cm") || setItemContains(set_300c, "4 3/4") || setItemContains(set_300c, "4 ¾") || setItemContains(set_300c, "4¾") ||  setItemContains(set_300c, "4 ³/₄") || setItemContains(set_300c, "4³/₄")
                ) {
                    result.add("Audio CD");
                }
            }
        }

        // Audiocassette:
        // (RecType=j OR RecType=i OR GMD="sound*")
        // AND
        // (Medium2=audiocassette OR Medium2=cassette? OR Medium=ss)
        if (
           ( itemEquals(recType, "j") || itemEquals(recType, "i") || setItemContains(set_gmd, "sound") )
           &&
           ( setItemContainsTokenized(set_medium2, "cassette") || itemEquals(medium, "ss") )
        ) {
           result.add("Audiocassette");
        }

        // Vinyl LP:
        // (RecType=j OR RecType=i OR GMD="sound*" OR Medium=sd)
        // AND
        // (Medium2="33 1/3")
        if (
           ( itemEquals(recType, "j") || itemEquals(recType, "i") || setItemContains(set_gmd, "sound") || itemEquals(medium, "sd") )
           &&
           ( setItemContains(set_medium2, "33 1/3") || setItemContains(set_medium2, "33 ⅓") || setItemContains(set_medium2, "33⅓") ||  setItemContains(set_medium2, "33 ¹/₃") || setItemContains(set_medium2, "33¹/₃"))
        ) {
           result.add("Vinyl LP");
        }


        /****
            Braille:

            (006/06=f AND 006/00=acdijpst)
            OR
            (006/12=f AND 006/00=efgkor)
            OR
            (008/23=f AND RecType=cdijp)                                 
            OR
            (008/23=f AND RecType=at AND BibLvl=acdm)   
            OR                                       
            (008/23=f AND RecType=a AND BibLvl=bis)    
            OR
            (008/29=f AND RecType=efgkor)                        
            OR
            term "braille" appears in 245h, 250a, 300abe, 340n, 440a, 490a, 655a
            OR
            (336b="tct" OR 336b="tcn" OR 336a="tactile text" OR 336a="tactile notated music") AND term "braille" appears in 650a
 
            Large Print:

            (006/06=d AND 006/00=acdijpst)
            OR
            (006/12=d AND 006/00=efgkor)
            OR
            (008/23=d AND RecType=cdijp)                                     
            OR
            (008/23=d AND RecType=at AND BibLvl=acdm) 
            OR                                       
            (008/23=d AND RecType=a AND BibLvl= bis)             
            OR
            (008/29=d AND RecType=efgkor)                   
            OR
            terms "giant print" OR "large print" appear in 250a, 300abe, 340n, 440a, 490a
 
            Notes:
            RecType is LDR/06
            BibLvl is LDR/07
            RecType, BibLvl, 006, and 008 letters represent single characters a, b, c, d, e…
            
            note the following equivalents: set_gmd = 245h, set_blp = (250a, 300abe, 340n, 440a, 490a), set_655a = 655a
         ****/
        Set set_blp = new LinkedHashSet();
        addSubfieldDataToSet(record, set_blp, "250", "a");
        addSubfieldDataToSet(record, set_blp, "300", "a");
        addSubfieldDataToSet(record, set_blp, "300", "b");
        addSubfieldDataToSet(record, set_blp, "300", "e");
        addSubfieldDataToSet(record, set_blp, "340", "n");
        addSubfieldDataToSet(record, set_blp, "440", "a");
        addSubfieldDataToSet(record, set_blp, "490", "a");

        Set set_650a = new LinkedHashSet();
        addSubfieldDataToSet(record, set_650a, "650", "a");

        Set set_655a = new LinkedHashSet();
        addSubfieldDataToSet(record, set_655a, "655", "a");

        Set set_336a = new LinkedHashSet();
        addSubfieldDataToSet(record, set_336a, "336", "a");

        Set set_336b = new LinkedHashSet();
        addSubfieldDataToSet(record, set_336b, "336", "b");

        String _008_23 = getFirstFieldValLowerCase(record, "008[23]");
        String _006_23 = getFirstFieldValLowerCase(record, "006[23]");
        String _006_00 = getFirstFieldValLowerCase(record, "006[0]");
        String _006_06 = getFirstFieldValLowerCase(record, "006[6]");
        String _008_29 = getFirstFieldValLowerCase(record, "008[29]");
        String _006_12 = getFirstFieldValLowerCase(record, "006[12]");

        // Begin "Braille"
       if (
           (itemEquals(_006_06,"f") && (itemEquals(_006_00,"a") || itemEquals(_006_00,"c") || itemEquals(_006_00,"d") || itemEquals(_006_00,"i") || itemEquals(_006_00,"j") || itemEquals(_006_00,"p") || itemEquals(_006_00,"s") || itemEquals(_006_00,"t")))
              ||
           (itemEquals(_006_12,"f") && (itemEquals(_006_00,"e") || itemEquals(_006_00,"f") || itemEquals(_006_00,"g") || itemEquals(_006_00,"k") || itemEquals(_006_00,"o") || itemEquals(_006_00,"r")))
              ||
           (itemEquals(_008_23,"f") && (itemEquals(recType,"c") || itemEquals(recType,"d") || itemEquals(recType,"i") || itemEquals(recType,"j") || itemEquals(recType,"p")))
              ||
           (itemEquals(_008_23,"f") && (itemEquals(recType,"a") || itemEquals(recType,"t")) && (itemEquals(bibLvl,"a") || itemEquals(bibLvl,"c") || itemEquals(bibLvl,"d") || itemEquals(bibLvl,"m")))
              ||
           (itemEquals(_008_23,"f") && itemEquals(recType,"a") && (itemEquals(bibLvl,"b") || itemEquals(bibLvl,"i") || itemEquals(bibLvl,"s")))
              ||
           (itemEquals(_008_29,"f") && (itemEquals(recType,"e") || itemEquals(recType,"f") || itemEquals(recType,"g") || itemEquals(recType,"k") || itemEquals(recType,"o") || itemEquals(recType,"r")))
              ||
           (setItemContains(set_gmd, "braille") || setItemContains(set_blp, "braille") || setItemContains(set_655a, "braille"))
              ||
           (setItemContains(set_650a, "braille") && (setItemContains(set_336b, "tct") || setItemContains(set_336b, "tcn") || setItemContains(set_336a, "tactile text") || setItemContains(set_336a, "tactile notated music") || setItemContains(set_336a, "tactile text")))
       ) {
           result.add("Braille");
       } // End "Braille"

        // Begin "Large Print"
       if (
           (itemEquals(_006_06,"d") && (itemEquals(_006_00,"a") || itemEquals(_006_00,"c") || itemEquals(_006_00,"d") || itemEquals(_006_00,"i") || itemEquals(_006_00,"j") || itemEquals(_006_00,"p") || itemEquals(_006_00,"s") || itemEquals(_006_00,"t")))
              ||
           (itemEquals(_006_12,"d") && (itemEquals(_006_00,"e") || itemEquals(_006_00,"f") || itemEquals(_006_00,"g") || itemEquals(_006_00,"k") || itemEquals(_006_00,"o") || itemEquals(_006_00,"r")))
              ||
           (itemEquals(_008_23,"d") && (itemEquals(recType,"c") || itemEquals(recType,"d") || itemEquals(recType,"i") || itemEquals(recType,"j") || itemEquals(recType,"p")))
              ||
           (itemEquals(_008_23,"d") && (itemEquals(recType,"a") || itemEquals(recType,"t")) && (itemEquals(bibLvl,"a") || itemEquals(bibLvl,"c") || itemEquals(bibLvl,"d") || itemEquals(bibLvl,"m")))
              ||
           (itemEquals(_008_23,"d") && itemEquals(recType,"a") && (itemEquals(bibLvl,"b") || itemEquals(bibLvl,"i") || itemEquals(bibLvl,"s")))
              ||
           (itemEquals(_008_29,"d") && (itemEquals(recType,"e") || itemEquals(recType,"f") || itemEquals(recType,"g") || itemEquals(recType,"k") || itemEquals(recType,"o") || itemEquals(recType,"r")))
              ||
           (setItemContains(set_blp, "giant print") || setItemContains(set_blp, "large print"))
       ) {
           result.add("Large Print");
       } // End "Large Print"

       /**** CD-ROM format facet request:

       (RecType=m and 008/23=q) OR (007/00=c AND 007/01=o) OR (007/00=c AND 007/04=g) OR (006/00=m AND 006/06=q) OR 245h=”computer file” OR 337a=”computer” OR 337b=c OR 338a=“computer disc”
       OR 338b=cd OR (300c=4/3/4 in. OR 12 cm) OR (the terms “computer laser optical disc” OR “computer optical disc” OR “computer disc” appear in 300a, 300b)

       AND
       The term “CD-ROM” appears in 300a, 300b, 300e, 338$3, 347b, 538a, 690a, 753a
       ****/

      String _007_04 = getFirstFieldValLowerCase(record, "007[4]");

      Set set_337a = new LinkedHashSet();
      addSubfieldDataToSet(record, set_337a, "337", "a");

      Set set_337b = new LinkedHashSet();
      addSubfieldDataToSet(record, set_337b, "337", "b");

      Set set_338a = new LinkedHashSet();
      addSubfieldDataToSet(record, set_338a, "338", "a");

      Set set_338b = new LinkedHashSet();
      addSubfieldDataToSet(record, set_338b, "338", "b");

      Set set_300ab = new LinkedHashSet();
      addSubfieldDataToSet(record, set_300ab, "300", "a");
      addSubfieldDataToSet(record, set_300ab, "300", "b");

      Set set_300abe_3383_347b_538a_690a_753a = new LinkedHashSet();
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "300", "a");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "300", "b");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "300", "e");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "338", "3");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "347", "b");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "538", "a");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "690", "a");
      addSubfieldDataToSet(record, set_300abe_3383_347b_538a_690a_753a, "753", "a");

      if (
           (
              (itemEquals(recType,"m") && itemEquals(_008_23,"q"))
                 ||
              (_007_00.equals("c") && _007_01.equals("o"))
                 ||
              (_007_00.equals("c") && _007_04.equals("g"))
                 ||
              (_006_00.equals("m") && _006_06.equals("q"))
                 ||
              setItemContains(set_gmd, "computer file")
                 ||
              setItemContains(set_337a, "computer")
                 ||
              setItemContains(set_337b, "c")
                 ||
              setItemContains(set_338a, "computer disc")
                 ||
              setItemContains(set_338b, "cd")
                 ||
              (setItemContains(set_300c, "12 cm") || setItemContains(set_300c, "4 3/4") || setItemContains(set_300c, "4 ¾") || setItemContains(set_300c, "4¾") ||  setItemContains(set_300c, "4 ³/₄") || setItemContains(set_300c, "4³/₄")) 
                 ||
              (setItemContains(set_300ab, "computer laser optical disc") || setItemContains(set_300ab, "computer optical disc") || setItemContains(set_300ab, "computer disc"))
           )
           &&
           (
              setItemContains(set_300abe_3383_347b_538a_690a_753a, "CD-ROM")
           )

        ) {
           result.add("CD-ROM");
        }



//logger.debug("Formats results = " + result + "\n");
        return result;
    }





    public void addSubfieldDataToSet(Record record, Set set, String field, String subfield)
    {
        if (field.equals("000"))
        {
            Leader leader = record.getLeader();
            String val = leader.toString();
            set.add(val);
            return;
        }
        List fields = record.getVariableFields(field);
        Iterator fldIter = fields.iterator();
        while (fldIter.hasNext())
        {
            if (subfield != null)
            {
                DataField dfield = (DataField) fldIter.next();
                List sub = dfield.getSubfields(subfield.charAt(0));
                Iterator iter = sub.iterator();
                while (iter.hasNext())
                {
                    Subfield s = (Subfield) (iter.next());
                    String data = s.getData();
                    data = Utils.cleanData(data);
                    set.add(data);
                }
            }
            else
            {
                ControlField cfield = (ControlField) fldIter.next();
                set.add(cfield.getData());
            }
        }
    }


    ////////////
    // String/pattern matching methods
    ///////////

    // for sets
    public static boolean setItemMatches(Set set, String pattern, Comparitor c)
    {
        if (set.isEmpty()) {
                return(false);
        }

        Iterator iter = set.iterator();

        while (iter.hasNext())
        {
            String value = (String)iter.next();

            if (c.compare(value, pattern)) {
               return true;
            }

        }
        return false;
    }
    public static boolean setItemEquals(Set set, String pattern)
    {
       return setItemMatches(set, pattern, equalsComparitor);
    }
    public static boolean setItemBeginsWith(Set set, String pattern)
    {
       return setItemMatches(set, pattern, beginsWithComparitor);
    }
    public static boolean setItemContains(Set set, String pattern)
    {
       return setItemMatches(set, pattern, containsComparitor);
    }
    public static boolean setItemContainsTokenized(Set set, String pattern)
    {
       return setItemMatches(set, pattern, tokenizedContainsComparitor);
    }




    // for individual items
    public static boolean itemMatches(String item, String pattern, Comparitor c)
    {
       return c.compare(item, pattern);
    }
    public static boolean itemEquals(String item, String pattern) {
       return itemMatches(item, pattern, equalsComparitor);
    }
    public static boolean itemBeginsWith(String item, String pattern) {
       return itemMatches(item, pattern, beginsWithComparitor);
    }



}
