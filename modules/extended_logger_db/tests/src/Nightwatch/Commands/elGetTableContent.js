module.exports = class ElGetTableContent {
  /**
   * Gets table content as a 2D array where each row is an array of cell values.
   *
   * @param {string} selector
   *   CSS selector for the table element.
   * @param {function} [callback]
   *   A callback which will be called when getting table content is finished.
   * @return {array}
   *   The table data as a 2D array.
   */
  async command(selector, callback = undefined) {
    let tableData = [];

    // Use executeScript to run JavaScript in the browser context
    await this.api.executeScript(
      (tableSelector) => {
        const table = document.querySelector(tableSelector);
        if (!table) {
          return [];
        }

        const rows = table.querySelectorAll('tr');
        const data = [];

        for (let i = 0; i < rows.length; i++) {
          const row = rows[i];
          const cells = row.querySelectorAll('td, th');
          const rowData = [];

          for (let j = 0; j < cells.length; j++) {
            rowData.push(cells[j].textContent.trim());
          }

          if (rowData.length > 0) {
            data.push(rowData);
          }
        }

        return data;
      },
      [selector],
      (result) => {
        if (result.value) {
          tableData = result.value;
        }
      },
    );

    if (typeof callback === 'function') {
      const self = this;
      callback.call(self, tableData);
    }
    return tableData;
  }
};
