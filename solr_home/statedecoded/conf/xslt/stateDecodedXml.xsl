<?xml version="1.0" encoding="us-ascii"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="2.0">
  <xsl:output media-type="text/xml" method="xml" indent="yes"/>

  <xsl:template match="/">
    <add>
      <xsl:apply-templates select="law"/>
    </add>
  </xsl:template>

  <xsl:template match="law">
    <doc>
      <field name="id">l_<xsl:value-of select="url"/></field>
      <field name="catch_line"><xsl:value-of select="catch_line"/></field>
      <field name="text"><xsl:apply-templates select="text"/></field>
      <field name="section"><xsl:value-of select="token"/></field>
      <xsl:apply-templates select="structure"/>
      <xsl:apply-templates select="tags"/>
      <xsl:apply-templates select="refers_to"/>
      <xsl:apply-templates select="referred_to_by"/>
      <field name="type">law</field>
    </doc>
  </xsl:template>

  <xsl:template match="text">
    <xsl:apply-templates mode="escape"/>
  </xsl:template>

  <xsl:template match="tags">
    <xsl:for-each select="tag">
      <field name="tags">
        <xsl:value-of select="current()"/>
      </field>
    </xsl:for-each>
  </xsl:template>

  <xsl:template match="refers_to">
    <xsl:for-each select="reference">
      <field name="refers_to">
        <xsl:value-of select="current()"/>
      </field>
    </xsl:for-each>
  </xsl:template>

  <xsl:template match="referred_to_by">
    <xsl:for-each select="reference">
      <field name="referred_to_by">
        <xsl:value-of select="current()"/>
      </field>
    </xsl:for-each>
  </xsl:template>

  <xsl:template match="structure">
    <field name="structure">
      <xsl:for-each select="unit">
        <xsl:value-of select="current()"/>
        <xsl:if test="not(position() = last())">/</xsl:if>
      </xsl:for-each>
    </field>
  </xsl:template>






  <xsl:template match="*" mode="escape">
    <!-- Begin opening tag -->
    <xsl:text>&lt;</xsl:text>
    <xsl:value-of select="name()"/>

    <!-- Attributes -->
    <xsl:for-each select="@*">
      <xsl:text> </xsl:text>
      <xsl:value-of select="name()"/>
      <xsl:text>='</xsl:text>
      <xsl:call-template name="escape-xml">
        <xsl:with-param name="text" select="."/>
      </xsl:call-template>
      <xsl:text>'</xsl:text>
    </xsl:for-each>

    <!-- End opening tag -->
    <xsl:text>&gt;</xsl:text>

    <!-- Content (child elements, text nodes, and PIs) -->
    <xsl:apply-templates select="node()" mode="escape" />

    <!-- Closing tag -->
    <xsl:text>&lt;/</xsl:text>
    <xsl:value-of select="name()"/>
    <xsl:text>&gt;</xsl:text>
  </xsl:template>

  <xsl:template match="text()" mode="escape">
    <xsl:call-template name="escape-xml">
      <xsl:with-param name="text" select="."/>
    </xsl:call-template>
  </xsl:template>

  <xsl:template match="processing-instruction()" mode="escape">
    <xsl:text>&lt;?</xsl:text>
    <xsl:value-of select="name()"/>
    <xsl:text> </xsl:text>
    <xsl:call-template name="escape-xml">
      <xsl:with-param name="text" select="."/>
    </xsl:call-template>
    <xsl:text>?&gt;</xsl:text>
  </xsl:template>

  <!-- applies escape-xml-single to chunks of $batchSize
       strings. The chunks are necesarry to avoid stackoverflows
       on very large strings
  -->

  <xsl:template name="escape-xml">
     <xsl:param name="text"/>
     <xsl:variable name="textLen" select="string-length($text)"/>
     <xsl:variable name="batchSize" select="100"/>
     <xsl:variable name="amountToProcess">
     <xsl:choose>
         <xsl:when test="$textLen &lt;= $batchSize">
            <xsl:value-of select="$textLen"/>
         </xsl:when>
         <xsl:otherwise>
            <xsl:value-of select="$batchSize"/>
         </xsl:otherwise>
     </xsl:choose>
      </xsl:variable>
      <!--<xsl:message>TEXTLEN: <xsl:value-of select="$textLen"/></xsl:message>
     <xsl:message>AMOUNT TO PROCESS <xsl:value-of select="$amountToProcess"/></xsl:message>-->

         <!--"blah>" "<cat meow"-->
         <xsl:variable name="substr" select="substring($text, 1, $amountToProcess)"/>
         <xsl:variable name="tail" select="substring($text, $amountToProcess + 1)"/>
         <xsl:call-template name="escape-xml-single">
            <xsl:with-param name="text" select="$substr"/>
         </xsl:call-template>
         <xsl:if test="$tail != ''">
             <xsl:call-template name="escape-xml">
                <xsl:with-param name="text" select="$tail"/>
             </xsl:call-template>
         </xsl:if>
  </xsl:template>

  <!-- tail recursion single character at a time
       escaping as needed. This should be only
       called directly by escape-xml
  -->
  <xsl:template name="escape-xml-single">
    <xsl:param name="text"/>
    <!--<xsl:message><xsl:value-of select="local-name()"/> TEXT <xsl:value-of select="$text"/></xsl:message>-->
    <xsl:if test="$text != ''">
          <xsl:variable name="head" select="substring($text, 1, 1)"/>
          <xsl:variable name="tail" select="substring($text, 2)"/>
           <!-- Emit the correct escap sequence for this character -->
          <xsl:choose>
             <xsl:when test="$head = '&amp;'">&amp;amp;</xsl:when>
             <xsl:when test="$head = '&lt;'">&amp;lt;</xsl:when>
             <xsl:when test="$head = '&gt;'">&amp;gt;</xsl:when>
             <xsl:when test="$head = '&quot;'">&amp;quot;</xsl:when>
             <xsl:when test="$head = &quot;&apos;&quot;">&amp;apos;</xsl:when>
             <xsl:otherwise><xsl:value-of select="$head"/></xsl:otherwise>
         </xsl:choose>
       <!-- recurse on the remaining characters -->
       <xsl:call-template name="escape-xml">
        <xsl:with-param name="text" select="$tail"/>
      </xsl:call-template>
    </xsl:if>
  </xsl:template>


</xsl:stylesheet>
