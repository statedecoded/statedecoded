package com.o19s;

/*
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import java.io.IOException;
import java.io.Reader;
import java.util.ArrayList;
import java.util.Formatter;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

import org.apache.lucene.analysis.Tokenizer;
import org.apache.lucene.analysis.core.KeywordTokenizer;
import org.apache.lucene.analysis.tokenattributes.CharTermAttribute;
import org.apache.lucene.analysis.tokenattributes.OffsetAttribute;
import org.apache.lucene.analysis.tokenattributes.PositionIncrementAttribute;

/**
 * Tokenizer for path-like hierarchies.
 * <p>
 * Take something like:
 *
 * <pre>
 *  /something/something/else
 * </pre>
 *
 * and make:
 *
 * <pre>
 *  /something
 *  /something/something
 *  /something/something/else
 * </pre>
 */
public class RegexPathHierarchyTokenizer extends Tokenizer {

  
  public RegexPathHierarchyTokenizer(Reader input, String delimiter) {
    this(AttributeFactory.DEFAULT_ATTRIBUTE_FACTORY, input, DEFAULT_BUFFER_SIZE, delimiter, DEFAULT_DEPTH_PREFIX_NUM_CHARS);
  }
  
  public RegexPathHierarchyTokenizer(Reader input, String delimiter, int depthPrefixNumChars) {
    this(AttributeFactory.DEFAULT_ATTRIBUTE_FACTORY, input, DEFAULT_BUFFER_SIZE, delimiter, depthPrefixNumChars);
  }
      
  public RegexPathHierarchyTokenizer(Reader input, int bufferSize, String delimiter, int depthPrefixNumChars) {
    this(AttributeFactory.DEFAULT_ATTRIBUTE_FACTORY, input, bufferSize, delimiter, depthPrefixNumChars);
  }

  private static final int DEFAULT_BUFFER_SIZE = 1024;
  public static final String DEFAULT_DELIMITER = "/";
  public static final int DEFAULT_DEPTH_PREFIX_NUM_CHARS = 0;

  //non-stateful
  private final Pattern delimiter;
  int depthPrefixNumChars;
  private Formatter termAttFormatter;
  private String zeroPaddingString;
  
  //stateful
  private final Matcher matcher;
  private final KeywordTokenizer keyWordTokenizer;
  private final CharTermAttribute keyWordTokenizerTermAtt;
  private int currentStart = 0;
  private int currentEnd = 0;
  private boolean done = false;
  private int depth = 0;
  private final StringBuffer termBuffer;
  private final CharTermAttribute termAtt = addAttribute(CharTermAttribute.class);
  private final OffsetAttribute offsetAtt = addAttribute(OffsetAttribute.class);
  private final PositionIncrementAttribute posAtt = addAttribute(PositionIncrementAttribute.class);
  
  public RegexPathHierarchyTokenizer
      (AttributeFactory factory, Reader input, int bufferSize, String delimiter, int depthPrefixNumChars) {
    
    super(factory, input);
    if (bufferSize < 0) {
      throw new IllegalArgumentException("bufferSize cannot be negative");
    }

    if (depthPrefixNumChars > 0) {      
      termAttFormatter = new Formatter(termAtt);
      zeroPaddingString = "%0"+depthPrefixNumChars+"d";
    }

    termBuffer = new StringBuffer(bufferSize);
    termAtt.resizeBuffer(bufferSize);
    this.delimiter = Pattern.compile(delimiter);
    this.depthPrefixNumChars = depthPrefixNumChars;
    
    keyWordTokenizer = new KeywordTokenizer(input);
    keyWordTokenizerTermAtt = keyWordTokenizer.addAttribute(CharTermAttribute.class);
    
    matcher = this.delimiter.matcher(keyWordTokenizerTermAtt);
  }

  
  @Override
  public final boolean incrementToken() throws IOException {
    if(done) return false;
    clearAttributes();
    if(depth == 0) {
      keyWordTokenizer.incrementToken();
      matcher.reset(keyWordTokenizerTermAtt);
      posAtt.setPositionIncrement(1);
    } else {
      posAtt.setPositionIncrement(0); //I thought that clearAttributes would reset this to 0!?
    }
    if(matcher.find())  
    { 
      currentEnd = matcher.start();
      termBuffer.append(keyWordTokenizerTermAtt.subSequence(currentStart, currentEnd));
      offsetAtt.setOffset(0, currentEnd);
      currentStart = currentEnd;
    } else { 
      termBuffer.append(keyWordTokenizerTermAtt.subSequence(currentStart, keyWordTokenizerTermAtt.length()));
      done = true;
      offsetAtt.setOffset(0, keyWordTokenizerTermAtt.length());
    }
    if(depthPrefixNumChars > 0) {
      termAttFormatter.format(zeroPaddingString,depth);
      termAtt.append(termBuffer);
    } else {
      termAtt.append(termBuffer);
    }
    depth++;
    return true;
  }

  @Override
  public final void end() throws IOException {
    super.end();
    keyWordTokenizer.end();
  }

  @Override
  public void reset() throws IOException {
    super.reset();
    done = false;
    depth = 0;
    currentStart = 0;
    currentEnd = 0;
    termBuffer.delete(0,termBuffer.length());
    keyWordTokenizer.reset();
    keyWordTokenizer.setReader(input);
  }
}
