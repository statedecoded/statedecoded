package com.o19s;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertNotSame;

import java.io.IOException;
import java.io.OutputStream;
import java.io.Reader;
import java.io.StringReader;

import org.apache.lucene.analysis.TokenStream;
import org.apache.lucene.analysis.path.PathHierarchyTokenizer;
import org.apache.lucene.analysis.tokenattributes.CharTermAttribute;
import org.apache.lucene.analysis.tokenattributes.OffsetAttribute;
import org.apache.lucene.analysis.tokenattributes.PositionIncrementAttribute;
import org.junit.Test;

public class RegexPathHierarchyTokenizerTest {

 
  //replace $n
  //replace other
  //replace = ""
  //replace unspecified
  //pattern is complex
  //pattern = ""
  //ends with - or space
  //add
  

  @Test
  public void testBasic() throws IOException {
    TokenStream t = new RegexPathHierarchyTokenizer(new StringReader("14-32.43-25") , "[-.]");
    /*
     * Should become
     * 
     * 14
     * 14-32
     * 14-32.43
     * 14-32.43-25
     *  2  5  8  1
     */
    compareTokens(t,new String[]{"14","14-32","14-32.43","14-32.43-25"}, new int[]{1,0,0,0},new int[]{0,0,0,0},new int[]{2,5,8,11});  
  }
  
  
  @Test
  public void testPrefixSize1() throws IOException {
    TokenStream t = new RegexPathHierarchyTokenizer(new StringReader("14-32.43-25"), "[-.]", 1);
    /*
     * Should become
     * 
     * 014
     * 114-32
     * 214-32.43
     * 314-32.43-25
     *  2  5  8  1 
     */
    compareTokens(t,new String[]{"014","114-32","214-32.43","314-32.43-25"}, new int[]{1,0,0,0},new int[]{0,0,0,0},new int[]{2,5,8,11});  
  }
  

  @Test
  public void testHardRegex() throws IOException {
    TokenStream t = new RegexPathHierarchyTokenizer(new StringReader("abcbddbadccabd"), "(\\w)\\1"); //splits on double letters
    /*
     * Should become
     * 
     * abcb
     * abcbddbad
     * abcbddbadccabd
     *    4    9    4 
     */
    compareTokens(t,new String[]{"abcb","abcbddbad","abcbddbadccabd"}, new int[]{1,0,0},new int[]{0,0,0},new int[]{4,9,14});  
  }
  
  
  public void compareTokens(TokenStream tokenStream, String[] desiredTokens) throws IOException {
    compareTokens(tokenStream, desiredTokens, null, null, null);
  }
  
  public void compareTokens(TokenStream tokenStream, String[] desiredTokens, 
      int[] desiredPosIncr, int[] desiredStartOffset, int[] desiredEndOffset) throws IOException {
    
    CharTermAttribute termAtt = tokenStream.addAttribute(CharTermAttribute.class);
    OffsetAttribute offsetAtt = tokenStream.addAttribute(OffsetAttribute.class);
    PositionIncrementAttribute posAtt = tokenStream.addAttribute(PositionIncrementAttribute.class);
    
    int i = 0;
    while (tokenStream.incrementToken()) {
      assertNotSame("Emitted more tokens than expected.",desiredTokens.length,i);
      int startOffset = offsetAtt.startOffset();
      int endOffset = offsetAtt.endOffset();
      int posIncr = posAtt.getPositionIncrement();
      String term = termAtt.toString();
      if(desiredTokens != null) {
        assertEquals("Wrong token.",desiredTokens[i],term);
      }
      if(desiredStartOffset != null) {
        assertEquals("Wrong start offset.",desiredStartOffset[i],startOffset);
      }
      if(desiredEndOffset != null) {
        assertEquals("Wrong end offset.",desiredEndOffset[i],endOffset);
      }
      if(desiredPosIncr != null) {
        assertEquals("Wrong position increment.",desiredPosIncr[i],posIncr);
      }
      i++;
    }
    assertEquals("Didn't emit all desired tokens.",desiredTokens.length,i);
    
  }
  
  
}
