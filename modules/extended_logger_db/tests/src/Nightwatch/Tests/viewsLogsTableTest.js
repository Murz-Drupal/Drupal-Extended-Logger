const assert = require('assert');

module.exports = {
  '@tags': ['extended_logger', 'extended_logger_db'],
  before(browser) {
    browser.drupalInstall({
      installProfile: 'extended_logger_db_test_profile',
    });
  },
  after(browser) {
    browser.drupalUninstall();
  },
  'Check the Views logs table content': async (browser) => {
    const logTableSelector = '.views-element-container table';
    const baseUrl = process.env.DRUPAL_TEST_BASE_URL;
    const basePath =
      new URL(baseUrl).pathname === '/' ? '' : new URL(baseUrl).pathname;

    browser
      .drupalRelativeURL('/non-existent-page-1')
      .thLogin('admin')
      .drupalRelativeURL('/non-existent-page-2')
      .drupalRelativeURL('/admin/reports/extended-logs')
      .waitForElementPresent(logTableSelector)
      .perform(async () => {
        const tableData = await browser.elGetTableContent(logTableSelector);
        // We have to output here.
        // eslint-disable-next-line no-console
        console.log('Table data:', tableData);

        const timeRegexp = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
        const expectedLogs = [
          [
            timeRegexp,
            'page not found',
            'warning',
            '{basePath}/non-existent-page-2',
            `ip:
{ip}
request_uri:
{baseUrl} / non - existent - page - 2
uid:
1`,
          ],
          [
            timeRegexp,
            'user',
            'info',
            'Session opened for admin.',
            `ip:
{ip}
request_uri:
{baseUrl} / test - helpers - functional / login / admin
uid:
1`,
          ],
          [
            timeRegexp,
            'page not found',
            'warning',
            '{basePath}/non-existent-page-1',
            `ip:
{ip}
request_uri:
{baseUrl} / non - existent - page - 1
uid:
0`,
          ],
        ];
        assert.ok(
          tableData.length >= expectedLogs.length,
          'Table has enough rows.',
        );

        // We need to use a for loop here because of await inside it.
        // eslint-disable-next-line no-restricted-syntax
        for (const [rowIndex, row] of tableData) {
          if (!expectedLogs[rowIndex]) {
            // eslint-disable-next-line no-continue
            continue;
          }
          const rowExpected = expectedLogs[rowIndex];
          // We need to use a for loop here because of await inside it.
          // eslint-disable-next-line no-restricted-syntax
          for (const [colIndex, textOriginal] of row) {
            let textExpected = rowExpected[colIndex];
            // Replace IPv4, IPv6, and ::1 with {ip}
            const text = textOriginal.replace(
              /\n(?:\d{1,3}(?:\.\d{1,3}){3}|(?:[a-fA-F0-9:]+:+)+[a-fA-F0-9]+|::1)\n/,
              '\n{ip}\n',
            );
            if (textExpected instanceof RegExp) {
              // We have to output here.
              // eslint-disable-next-line no-console
              console.log(
                `Regexp check column ${colIndex} of row ${rowIndex}:`,
                textExpected,
              );
              assert.match(text, textExpected);
            } else {
              textExpected = textExpected.replace('{baseUrl}', baseUrl);
              textExpected = textExpected.replace('{basePath}', basePath);
              // We have to output here.
              // eslint-disable-next-line no-console
              console.log(
                `Text check column ${colIndex} of row ${rowIndex}:`,
                textExpected,
              );
              assert.equal(text, textExpected);
            }
          }
        }
      });
  },
};
