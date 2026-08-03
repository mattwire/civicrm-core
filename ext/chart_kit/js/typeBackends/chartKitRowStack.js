(function (d3, dc, ts) {
  "use strict";

  CRM.chart_kit = CRM.chart_kit || {};
  CRM.chart_kit.typeBackends = CRM.chart_kit.typeBackends || {};

  /**
   * Horizontal stacked bar chart.
   *
   * dc.js's RowChart (the only horizontal chart type it ships) has no
   * stacking support - it extends CapMixin(ColorMixin(MarginMixin)), not
   * StackMixin, and only ever reads a single group value per row. This is a
   * standalone chart class (ColorMixin(MarginMixin) - MarginMixin already
   * extends BaseMixin - the same base RowChart itself uses minus CapMixin)
   * that renders one segment per
   * 'y'-axis column per row, laid out with d3.stack() - the horizontal
   * equivalent of what the 'stack' backend (chartKitStack.js) does for
   * vertical bars.
   */
  class RowStackChart extends dc.ColorMixin(dc.MarginMixin) {

    constructor(parent, chartGroup) {
      super();

      this._gap = 5;
      this._maxBarHeight = 22;
      this._legendPosition = 'top';
      this._x = undefined;
      this._xAxis = d3.axisBottom();
      this._yColumns = [];
      this._wColumn = undefined;

      this.anchor(parent, chartGroup);
    }

    /**
     * Caps how tall a row's bar can get - remaining vertical space becomes
     * extra gap between rows instead of taller bars.
     * @param {Number} [height]
     * @returns {Number|RowStackChart}
     */
    maxBarHeight(height) {
      if (!arguments.length) {
        return this._maxBarHeight;
      }
      this._maxBarHeight = height;
      return this;
    }

    /**
     * 'top' or 'bottom' - where to place the horizontal, centered legend.
     * @param {String} [position]
     * @returns {String|RowStackChart}
     */
    legendPosition(position) {
      if (!arguments.length) {
        return this._legendPosition;
      }
      this._legendPosition = position;
      return this;
    }

    /**
     * The chart-kit 'y'-axis column configs to stack, in stacking order.
     * @param {Array} [cols]
     * @returns {Array|RowStackChart}
     */
    yColumns(cols) {
      if (!arguments.length) {
        return this._yColumns;
      }
      this._yColumns = cols;
      return this;
    }

    /**
     * The chart-kit 'w'-axis (category) column config.
     * @param {Object} [col]
     * @returns {Object|RowStackChart}
     */
    wColumn(col) {
      if (!arguments.length) {
        return this._wColumn;
      }
      this._wColumn = col;
      return this;
    }

    /**
     * Values in `this.data()` (from crossfilter's group.all()) are keyed by
     * each column's internal `.name` (e.g. 'y_0'), not its SQL select alias,
     * and for the categorical 'w' column the stored value is an *index* into
     * that column's own category list, not the display string - both need
     * to go through the column's own valueAccessor/getRenderedLabel rather
     * than being read off the raw group row directly.
     */
    _buildStack() {
      const rawGroups = this.data();
      const keys = this._yColumns.map((col) => col.name);
      const flat = rawGroups.map((g) => {
        const row = {};
        this._yColumns.forEach((col) => {
          row[col.name] = col.valueAccessor(g) || 0;
        });
        return row;
      });
      return {
        rawGroups,
        flat,
        series: d3.stack().keys(keys)(flat),
      };
    }

    _calculateAxisScale(maxValue) {
      const maxInt = Math.max(1, Math.ceil(maxValue || 0));
      this._x = d3.scaleLinear()
        .domain([0, maxInt])
        .range([0, this.effectiveWidth()]);
      // Case counts are always whole numbers - d3's default tick algorithm
      // will happily pick fractional steps (e.g. 0.5) for a small domain, so
      // ticks are set explicitly rather than left to .ticks(count). Keeps to
      // ~10 ticks max by widening the step on a larger domain.
      const step = Math.max(1, Math.ceil(maxInt / 10));
      this._xAxis
        .scale(this._x)
        .tickValues(d3.range(0, maxInt + 1, step))
        .tickFormat(d3.format('d'));
    }

    _drawAxis() {
      let axisG = this._g.select('g.axis');
      if (axisG.empty()) {
        axisG = this._g.append('g').attr('class', 'axis');
      }
      axisG.attr('transform', `translate(0, ${this.effectiveHeight()})`);
      // Deliberately not dc.transition() here (or on the bars below): a
      // transition schedules its .attr() calls to interpolate over time
      // rather than applying them immediately, and if a second render fires
      // before the first transition completes (observed in practice - see
      // git history for this file), the interrupted elements are left with
      // no attributes at all, i.e. invisible. Correctness over animation.
      axisG.call(this._xAxis);
      // dc.css hardcodes both to plain black (".dc-chart .axis path, .dc-chart
      // .axis line { stroke: #000; }" - applies here since anchor() puts
      // this chart's root in the same .dc-chart-classed container as any
      // other dc.js chart type) - near-invisible against a dark theme.
      axisG.selectAll('path.domain, .tick line')
        .attr('stroke', 'var(--crm-border-color)')
        .attr('shape-rendering', 'crispEdges');

      // Vertical rule at x=0 separating the row labels from the bars -
      // there's no real y-scale/axis to draw (rows are manually
      // positioned), just this one boundary line.
      let yAxisLine = this._g.select('line.y-axis-line');
      if (yAxisLine.empty()) {
        yAxisLine = this._g.append('line').attr('class', 'y-axis-line');
      }
      yAxisLine
        .attr('x1', 0)
        .attr('x2', 0)
        .attr('y1', 0)
        .attr('y2', this.effectiveHeight())
        .attr('stroke', 'var(--crm-border-color)')
        .attr('shape-rendering', 'crispEdges');
    }

    /**
     * Greedily wraps `text` onto as many <tspan> lines as needed to fit
     * `maxWidth`, vertically centered on `centerY` (classic D3 word-wrap
     * pattern, adapted for text-anchor="end" and vertical centering rather
     * than a fixed top y). Line width is measured via the real, already
     * text-anchor/font-styled element (getComputedTextLength()), not
     * estimated, so it matches whatever font size/family actually applies.
     */
    _wrapLabel(textSelection, text, maxWidth, centerY) {
      const words = text.split(/\s+/).filter(Boolean).reverse();
      textSelection.text(null);
      const x = textSelection.attr('x');
      const lineHeightEm = 1.1;
      let line = [];
      const lines = [];
      let tspan = textSelection.append('tspan').attr('x', x);
      while (words.length) {
        const word = words.pop();
        line.push(word);
        tspan.text(line.join(' '));
        if (line.length > 1 && tspan.node().getComputedTextLength() > maxWidth) {
          line.pop();
          tspan.text(line.join(' '));
          lines.push(tspan);
          line = [word];
          tspan = textSelection.append('tspan').attr('x', x).text(word);
        }
      }
      lines.push(tspan);

      const startDy = -((lines.length - 1) / 2) * lineHeightEm;
      lines.forEach((ln, i) => {
        ln.attr('y', centerY).attr('dy', `${startDy + i * lineHeightEm + 0.35}em`);
      });
    }

    _doRender() {
      this.resetSvg();
      this.svg().classed('row-stack-chart-svg', true);
      // Two fixes as a real stylesheet rule (scoped to this chart's own
      // <svg> via the class above, not page-wide) rather than setting
      // things imperatively with .attr()/.style() after the fact:
      //
      // - dc.css hardcodes an 11px font-size on legend text, out of step
      //   with everything else in this chart (which just inherits the
      //   theme's --crm-font-size). _positionLegend()'s autoItemWidth
      //   measures each item via getBBox() *inside* Legend.render() itself,
      //   so whatever font-size is in effect at that exact synchronous
      //   moment is what determines the (correct) spacing - patching it in
      //   afterwards is too late and items overlap.
      // - civi-search-display-chart-kit.js's shared 'pretransition'
      //   listener sets every <text> element's color via
      //   .attr('fill', labelColor), not .style(). CSS var() references in
      //   an SVG presentation *attribute* (as opposed to a real style/CSS
      //   property) aren't resolved by the browser - since format.labelColor
      //   is a var() reference here (for theme-awareness), the attribute
      //   ends up holding that literal, unparseable string and silently
      //   falls back to black. Re-fixing this after the fact turned out to
      //   be unreliable too: dc.js only fires its 'renderlet'/postRender
      //   listeners after a transition's 'end' event, and that transition
      //   never actually completes for this chart (confirmed directly -
      //   dead in the water, not just occasionally slow). A stylesheet
      //   rule sidesteps timing entirely: presentation attributes are the
      //   lowest-priority source in the CSS cascade, so a plain selector
      //   here reliably wins over the broken .attr('fill', ...) regardless
      //   of when either one is applied.
      this.svg().append('style').text(`
        .row-stack-chart-svg .dc-legend-item text { font-size: var(--crm-font-size); }
        .row-stack-chart-svg text { fill: var(--crm-text-color) !important; }
      `);
      this._g = this.svg()
        .append('g')
        .attr('transform', `translate(${this.margins().left},${this.margins().top})`);
      this._drawChart();
      return this;
    }

    _doRedraw() {
      this._drawChart();
      return this;
    }

    _drawChart() {
      const {rawGroups, series} = this._buildStack();

      const maxValue = d3.max(series, (s) => d3.max(s, (d) => d[1]));
      this._calculateAxisScale(maxValue);
      this._drawAxis();

      const n = rawGroups.length;
      // Cap bar height and give any leftover vertical space to the gaps
      // between rows instead, so bars stay slim regardless of chart height.
      const rawHeight = n ? (this.effectiveHeight() - (n + 1) * this._gap) / n : 0;
      const height = n ? Math.max(1, Math.min(rawHeight, this._maxBarHeight)) : 0;
      const gap = n ? (this.effectiveHeight() - height * n) / (n + 1) : this._gap;
      const colorScale = this.colors();
      const columns = this._yColumns;
      const wColumn = this._wColumn;

      let rows = this._g.selectAll('g.row-stack')
        .data(rawGroups, (d) => d.key);
      rows.exit().remove();
      rows = rows.enter()
        .append('g')
        .attr('class', 'row-stack')
        .merge(rows)
        .attr('transform', (d, i) => `translate(0, ${(i + 1) * gap + i * height})`);

      const chart = this;
      rows.each(function (rowDatum, rowIndex) {
        const segments = columns.map((col, colIndex) => ({
          col,
          x0: series[colIndex][rowIndex][0],
          x1: series[colIndex][rowIndex][1],
        }));

        let rects = d3.select(this).selectAll('rect.segment')
          .data(segments, (d) => d.col.name);
        rects.exit().remove();
        rects = rects.enter()
          .append('rect')
          .attr('class', 'segment')
          .merge(rects);

        rects
          .attr('y', 0)
          .attr('height', height)
          .attr('x', (d) => chart._x(d.x0))
          .attr('width', (d) => Math.max(0, chart._x(d.x1) - chart._x(d.x0)))
          .attr('fill', (d) => colorScale(d.col.label));

        let titles = rects.selectAll('title').data((d) => [d]);
        titles.enter().append('title').merge(titles)
          .text((d) => `${d.col.label}: ${d.x1 - d.x0}`);

        // Value overlaid on each segment, e.g. "25" - appended after its
        // rect (same paint-order reason as the row label below) and only
        // for segments with a nonzero value/some width to show it in.
        // Fill color isn't set here: civi-search-display-chart-kit.js's
        // generic 'pretransition' listener overwrites *every* <text>
        // element's fill with format.labelColor on every render regardless
        // of what's set here, so it'd have had no effect anyway.
        const valueLabels = segments.filter((d) => d.x1 - d.x0 > 0);
        let segmentLabels = d3.select(this).selectAll('text.segment-value')
          .data(valueLabels, (d) => d.col.name);
        segmentLabels.exit().remove();
        segmentLabels = segmentLabels.enter()
          .append('text')
          .attr('class', 'segment-value')
          .attr('text-anchor', 'middle')
          .merge(segmentLabels);
        segmentLabels
          .attr('x', (d) => (chart._x(d.x0) + chart._x(d.x1)) / 2)
          .attr('y', height / 2)
          .attr('dy', '0.35em')
          .text((d) => d.x1 - d.x0);
      });

      // Appended after the segments so it paints on top (SVG paints in DOM
      // order) - matches RowChart's own approach (label appended after its
      // rect for the same reason). Positioned in the left margin gutter
      // (negative x, relative to this row's own <g>, which starts at the
      // plot area's left edge) rather than overlaid on the bar itself.
      let labels = rows.selectAll('text.row-stack-label')
        .data((d) => [d]);
      labels.exit().remove();
      labels = labels.enter()
        .append('text')
        .attr('class', 'row-stack-label')
        .attr('text-anchor', 'end')
        .merge(labels);
      labels
        .attr('x', -6)
        .attr('y', height / 2);
      const maxLabelWidth = Math.max(20, this.margins().left - 12);
      const chartRef = this;
      labels.each(function (d) {
        chartRef._wrapLabel(d3.select(this), wColumn.getRenderedLabel(d), maxLabelWidth, height / 2);
      });

      this._positionLegend();
    }

    /**
     * dc.js's Legend renders as its own top-level <g> appended straight to
     * the chart's SVG (see Legend.render()), positioned via plain x()/y()
     * coordinates - not relative to this chart's own margins, and with no
     * built-in "top"/"bottom"/"centered" concept (only 'right' gets special
     * handling, in the shared civi-search-display-chart-kit.js wrapper).
     * Rendered once here (before the caller's own render()/redraw() renders
     * it "for real") purely to measure its actual width via getBBox(), so it
     * can be centered horizontally - text width isn't known ahead of time.
     */
    _positionLegend() {
      const legend = this.legend();
      if (!legend) {
        return;
      }
      legend
        .horizontal(true)
        .autoItemWidth(true)
        .legendWidth(this.width())
        .gap(10);

      const y = this._legendPosition === 'bottom' ? this.height() - this.margins().bottom + 25 : 4;
      legend.x(0).y(y).render();

      const bbox = legend._g.node().getBBox();
      legend.x(Math.max(0, (this.width() - bbox.width) / 2));
    }

    legendables() {
      const colorScale = this.colors();
      return this._yColumns.map((col) => ({
        chart: this,
        name: col.label,
        color: colorScale(col.label),
      }));
    }

  }

  CRM.chart_kit.typeBackends.rowStack = {
    adminTemplate: '~/crmChartKitAdmin/typeBackends/chartKitRowAdmin.html',

    getInitialDisplaySettings: () => ({}),

    getAxes: () => ({
      'w': {
        label: ts('Category'),
        scaleTypes: ['categorical'],
        reduceTypes: ['list'],
        isDimension: true,
      },
      'y': {
        key: 'y',
        label: ts('Values'),
        sourceDataTypes: ['Integer', 'Money', 'Boolean', 'Float', 'Double'],
        multiColumn: true,
        colorType: 'one-per-column',
      },
    }),

    hasCoordinateGrid: () => false,

    showLegend: (displayCtrl) => (displayCtrl.getColumnsForAxis('y').length > 1 && displayCtrl._settings.showLegend && displayCtrl._settings.showLegend !== 'none'),

    getChartConstructor: () => ((parent, chartGroup) => new RowStackChart(parent, chartGroup)),

    loadChartData: (displayCtrl) => {
      displayCtrl.chart
        .dimension(displayCtrl.dimension)
        .group(displayCtrl.group);

      // civi-search-display-chart-kit.js only applies format.padding as
      // margins() from within formatCoordinateGrid(), which is gated by
      // hasCoordinateGrid() - false for this chart (same as 'row'), so it's
      // never called generically. Apply it here instead, or format.padding
      // (including the room reserved for the left-side row labels) is
      // silently ignored in favour of dc.js's hardcoded default margins.
      if (displayCtrl._settings.format && displayCtrl._settings.format.padding) {
        displayCtrl.chart.margins(displayCtrl._settings.format.padding);
      }

      const yAxisColumns = displayCtrl.getColumnsForAxis('y');
      displayCtrl.chart.yColumns(yAxisColumns);
      displayCtrl.chart.wColumn(displayCtrl.getFirstColumnForAxis('w'));
      displayCtrl.chart.colors(displayCtrl.buildColumnColorScale(yAxisColumns));
      if (['top', 'bottom'].includes(displayCtrl._settings.showLegend)) {
        displayCtrl.chart.legendPosition(displayCtrl._settings.showLegend);
      }
      if (displayCtrl._settings.maxBarHeight) {
        displayCtrl.chart.maxBarHeight(displayCtrl._settings.maxBarHeight);
      }
    },
  };

})(CRM.chart_kit.d3, CRM.chart_kit.dc, CRM.ts('chart_kit'));
