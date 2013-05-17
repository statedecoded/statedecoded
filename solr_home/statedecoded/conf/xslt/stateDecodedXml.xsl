<?xml version="1.0" encoding="us-ascii"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <xsl:output media-type="text/xml" method="xml" indent="yes"/>

  <xsl:template match="/">
    <add>
      <xsl:apply-templates select="law"/>
    </add>
  </xsl:template>
 
  <xsl:template match="law">
    <doc>
        <field name="id"><xsl:value-of select="@id"/></field>
        <field name="catch_line"><xsl:value-of select="catch_line"/></field>
        <field name="text"><xsl:value-of select="text"/></field>
        <field name="section"><xsl:value-of select="section_number"/></field>
        <xsl:apply-templates select="structure"/>
    </doc>
  </xsl:template>

  <xsl:template match="structure">
    <xsl:message>Matched Structure</xsl:message>
    <field name="structure">
        <xsl:for-each select="unit">
        <xsl:value-of select="current()"/> 
        <xsl:if test="not(position() = last())">/</xsl:if>

      </xsl:for-each>
    </field>
  </xsl:template>
 
</xsl:stylesheet>
