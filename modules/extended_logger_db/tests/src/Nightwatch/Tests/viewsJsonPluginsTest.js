const assert = require('assert');

const baseUrl = process.env.DRUPAL_TEST_BASE_URL;
const baseUrlPath = new URL(baseUrl).pathname;
const basePath = baseUrlPath === '/' ? '' : baseUrlPath;

module.exports = {
  '@tags': [
    'extended_logger',
    'extended_logger_db',
    'extended_logger_db-matrix',
  ],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'extended_logger_db_test_profile',
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Test common field and filter types': async (browser) => {
    const submitModalWindow = () => {
      browser
        .waitForElementPresent(
          '.views-ui-dialog .form-actions .button--primary.ui-widget',
        )
        .click('.views-ui-dialog .form-actions .button--primary.ui-widget')
        .waitForElementNotPresent(
          '.views-ui-dialog .form-actions .button--primary.ui-widget',
        );
    };

    const setFilterValues = ({ valuePath, value, operator }) => {
      browser
        .waitForElementPresent(
          '.views-ui-dialog [data-drupal-selector="edit-options-value-path"]',
        )
        // Set the value path (JSONPath).
        .setValue(
          '.views-ui-dialog [data-drupal-selector="edit-options-value-path"]',
          valuePath,
        );

      if (operator) {
        browser
          // Set the operator (e.g., equals, less than).
          .click(
            `.views-ui-dialog [data-drupal-selector="edit-options-operator"] [value="${operator}"]`,
          );
      }

      if (value) {
        browser.perform(async () => {
          // Set the value.
          if (
            await browser.isPresent({
              selector:
                '.views-ui-dialog [data-drupal-selector="edit-options-value-value"]',
              suppressNotFoundErrors: true,
              timeout: 1,
            })
          ) {
            browser.setValue(
              '.views-ui-dialog [data-drupal-selector="edit-options-value-value"]',
              value,
            );
          } else if (
            await browser.isPresent({
              selector:
                '.views-ui-dialog [data-drupal-selector="edit-options-value"]',
              suppressNotFoundErrors: true,
              timeout: 1,
            })
          ) {
            browser.setValue(
              '.views-ui-dialog [data-drupal-selector="edit-options-value"]',
              value,
            );
          } else {
            throw new Error('Value input field is not found in the dialog.');
          }
        });
      }
    };

    const addViewsField = ({
      fieldType, // field, filter, sort.
      pluginName,
      valuePath,
      value,
      operator,
    }) => {
      browser
        // Add the field.
        .click(`#views-add-${fieldType}`)
        .waitForElementPresent('.ui-dialog-content')
        .click(`.views-ui-dialog [value="extended_logger_logs.${pluginName}"]`)
        .thPerformAndWaitForReRender(() => {
          browser.click(
            '.views-ui-dialog .form-actions .button--primary.ui-widget',
          );
        }, '.views-ui-dialog .form-actions .button--primary.ui-widget');

      setFilterValues({ valuePath, value, operator });
      submitModalWindow();
    };

    const editViewsField = ({
      fieldType, // field, filter, sort.
      fieldId,
      valuePath,
      value,
      operator,
    }) => {
      const editUrl = `${
        basePath
      }/admin/structure/views/nojs/handler/extended_logger_logs/page/${
        fieldType
      }/${fieldId}`;
      browser
        // Add the field.
        .click(`a[href="${editUrl}"]`)
        .waitForElementPresent(
          '.views-ui-dialog .form-actions .button--primary.ui-widget',
        );
      setFilterValues({ valuePath, value, operator });
      submitModalWindow();
    };

    const executeAndGetTable = async () => {
      browser
        // Scroll to bottom to see the results in the screenshot.
        .execute('window.scrollTo(0, document.body.scrollHeight);')
        .waitForElementPresent('[data-drupal-selector="preview-submit"]')
        .thPerformAndWaitForReRender(() => {
          browser.click('[data-drupal-selector="preview-submit"]');
        }, '#views-ui-preview-form table:not([data-drupal-selector])')
        // Scroll to bottom to see the results in the screenshot.
        .execute('window.scrollTo(0, document.body.scrollHeight);');

      let tableData;
      await browser.perform(async () => {
        const query = await browser.getText('.views-query-info pre');
        // We need to output here.
        // eslint-disable-next-line no-console
        console.log('Views query:', query);

        // Check if table exists before trying to read it
        const tableExists = await browser.findElements(
          '#views-ui-preview-form table:not([data-drupal-selector])',
        );

        if (tableExists.length > 0) {
          tableData = await browser.elGetTableContent(
            '#views-ui-preview-form table:not([data-drupal-selector])',
          );
          // We need to output here.
          // eslint-disable-next-line no-console
          console.log('Table data:', tableData);
        } else {
          tableData = [];
        }
      });
      return tableData;
    };

    browser.window // visiting the first page on the domain, not before. // To include more details into the screenshot. Should be set after
      .setSize(1920, 2500)
      .execute("document.body.style.zoom='50%'")

      .thLogin('admin')
      .drupalRelativeURL(
        '/admin/reports/extended-logs?channel[]=extended_logger_db_test',
      )
      .perform(async () => {
        const tableData = await browser.elGetTableContent('table');
        // We need to output here.
        // eslint-disable-next-line no-console
        console.log('Initial table data:', tableData);
        assert.match(tableData[1][3], /^Test message 3/);
        assert.match(tableData[2][3], /^Test message 2/);
        assert.match(tableData[3][3], /^Test message 1/);
      });

    browser.drupalRelativeURL(
      '/admin/structure/views/view/extended_logger_logs/edit/page',
    );

    // Check the numeric filter with equals operator.
    browser.perform(async () => {
      addViewsField({
        fieldType: 'field',
        pluginName: 'log_value',
        valuePath: 'metadata.number_increment_2',
      });

      addViewsField({
        fieldType: 'field',
        pluginName: 'log_value',
        valuePath: '$.metadata.nested_values.key3',
      });

      addViewsField({
        fieldType: 'filter',
        pluginName: 'log_value_numeric',
        valuePath: '$.metadata.number_increment_1',
        value: '2',
        operator: '=',
      });

      const tableData = await executeAndGetTable();
      assert.strictEqual('102', tableData[1][5]);
    });

    // Check the numeric filter with "less than" operator.
    browser.perform(async () => {
      editViewsField({
        fieldType: 'filter',
        fieldId: 'log_value_numeric',
        valuePath: 'metadata.number_increment_1',
        value: '2',
        operator: '<',
      });

      const tableData = await executeAndGetTable();
      assert.strictEqual('101', tableData[1][5]);
    });

    // Check the numeric filter with "greater than" operator.
    browser.perform(async () => {
      editViewsField({
        fieldType: 'filter',
        fieldId: 'log_value_numeric',
        valuePath: 'metadata.number_increment_1',
        value: '2',
        operator: '>=',
      });

      const tableData = await executeAndGetTable();
      assert.strictEqual(3, tableData.length);
      assert.strictEqual('103', tableData[1][5]);
      assert.strictEqual('102', tableData[2][5]);
    });

    // Check the string filter with "contains" operator.
    browser.perform(async () => {
      addViewsField({
        fieldType: 'filter',
        pluginName: 'log_value_string',
        valuePath: 'metadata.nested_values.key3',
        value: 'message 3 key3',
        operator: 'contains',
      });
      const tableData = await executeAndGetTable();
      assert.strictEqual(2, tableData.length);
      assert.strictEqual('103', tableData[1][5]);
    });

    // Check the string filter with " not contains" operator.
    browser.perform(async () => {
      editViewsField({
        fieldType: 'filter',
        fieldId: 'log_value_string',
        valuePath: '$.metadata.nested_values.key3',
        value: 'message 3 key3',
        operator: 'not',
      });
      const tableData = await executeAndGetTable();
      assert.strictEqual(2, tableData.length);
      assert.strictEqual('102', tableData[1][5]);
    });

    browser
      .click('[data-drupal-selector="edit-actions-submit"]')
      .waitForElementPresent('body')
      .assert.textContains('[data-drupal-messages]', 'has been saved');
  },
};
