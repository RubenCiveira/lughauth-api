-- liquibase formatted sql

-- changeset auto.generated:1825492372-2
ALTER TABLE document_template_asset ALTER tenant SET DEFAULT null;

-- changeset auto.generated:1825492372-4
ALTER TABLE document_theme ALTER tenant SET DEFAULT null;

-- changeset auto.generated:1825492372-6
CREATE INDEX ST_TEMPLATE_VARIABLE_CODE_ASC ON document_template_variable(code);

-- changeset auto.generated:1825492372-7
CREATE TABLE document_snippet (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, tenant VARCHAR(255) NULL, CONSTRAINT PK_DOCUMENT_SNIPPET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-8
CREATE TABLE document_snippet_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, snippet VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_SNIPPET_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-9
CREATE TABLE document_snippet_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, subject VARCHAR(255) NULL, snippet VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_SNIPPET_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-10
CREATE TABLE document_theme_asset (uid VARCHAR(255) NOT NULL, version INT NOT NULL, code VARCHAR(255) NOT NULL, content VARCHAR(255) NOT NULL, enabled BIT DEFAULT 0 NULL, type VARCHAR(255) NOT NULL, theme VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME_ASSET PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-11
CREATE TABLE document_theme_version (uid VARCHAR(255) NOT NULL, version INT NOT NULL, channel VARCHAR(255) NOT NULL, content_html LONGTEXT NOT NULL, content_text LONGTEXT NULL, locale VARCHAR(255) NULL, theme VARCHAR(255) NOT NULL, CONSTRAINT PK_DOCUMENT_THEME_VERSION PRIMARY KEY (uid));

-- changeset auto.generated:1825492372-12
ALTER TABLE document_template_version ADD locale VARCHAR(255) NULL;

-- changeset auto.generated:1825492372-13
ALTER TABLE document_template_asset ADD template VARCHAR(255) NOT NULL;

-- changeset auto.generated:1825492372-14
CREATE INDEX FL_SNIPPET_ASSET_SNIPPETS ON document_snippet_asset(snippet);

-- changeset auto.generated:1825492372-15
CREATE INDEX FL_SNIPPET_TENANT ON document_snippet(tenant);

-- changeset auto.generated:1825492372-16
CREATE INDEX FL_SNIPPET_VERSION_SNIPPET ON document_snippet_version(snippet);

-- changeset auto.generated:1825492372-17
CREATE INDEX FL_TEMPLATE_ASSET_TEMPLATES ON document_template_asset(template);

-- changeset auto.generated:1825492372-18
CREATE INDEX FL_THEME_ASSET_CODE ON document_theme_asset(code);

-- changeset auto.generated:1825492372-19
CREATE INDEX FL_THEME_ASSET_THEME ON document_theme_asset(theme);

-- changeset auto.generated:1825492372-20
CREATE INDEX FL_THEME_VERSION_THEMES ON document_theme_version(theme);

-- changeset auto.generated:1825492372-21
CREATE INDEX ST_SNIPPET_ASSET_CODE_ASC ON document_snippet_asset(code);

-- changeset auto.generated:1825492372-22
CREATE INDEX ST_SNIPPET_ASSET_CODE_DESC ON document_snippet_asset(code DESC);

-- changeset auto.generated:1825492372-23
CREATE INDEX ST_SNIPPET_CODE_ASC ON document_snippet(code);

-- changeset auto.generated:1825492372-24
CREATE INDEX ST_SNIPPET_CODE_DESC ON document_snippet(code DESC);

-- changeset auto.generated:1825492372-25
CREATE INDEX ST_TEMPLATE_VARIABLE_CODE_DESC ON document_template_variable(code DESC);

-- changeset auto.generated:1825492372-26
CREATE INDEX ST_THEME_ASSET_CODE_DESC ON document_theme_asset(code DESC);

-- changeset auto.generated:1825492372-27
CREATE UNIQUE INDEX UK_SNIPPET_ASSET_CODE_SNIPPET ON document_snippet_asset(code, snippet);

-- changeset auto.generated:1825492372-28
CREATE UNIQUE INDEX UK_SNIPPET_CODE_TENANT ON document_snippet(code, tenant);

-- changeset auto.generated:1825492372-29
CREATE UNIQUE INDEX UK_TEMPLATE_ASSET_CODE_TEMPLATE_TENANT ON document_template_asset(code, template, tenant);

-- changeset auto.generated:1825492372-30
CREATE UNIQUE INDEX UK_THEME_ASSET_CODE_THEME ON document_theme_asset(code, theme);

-- changeset auto.generated:1825492372-31
CREATE UNIQUE INDEX UK_THEME_NAME_TENANT ON document_theme(name, tenant);

-- changeset auto.generated:1825492372-32
ALTER TABLE document_snippet_asset ADD CONSTRAINT FK_DOCUMENT_SNIPPET_ASSET_SNIPPET FOREIGN KEY (snippet) REFERENCES document_snippet (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-33
ALTER TABLE document_snippet ADD CONSTRAINT FK_DOCUMENT_SNIPPET_TENANT FOREIGN KEY (tenant) REFERENCES access_tenant (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-34
ALTER TABLE document_snippet_version ADD CONSTRAINT FK_DOCUMENT_SNIPPET_VERSION_SNIPPET FOREIGN KEY (snippet) REFERENCES document_snippet (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-35
ALTER TABLE document_template_asset ADD CONSTRAINT FK_DOCUMENT_TEMPLATE_ASSET_TEMPLATE FOREIGN KEY (template) REFERENCES document_template (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-36
ALTER TABLE document_theme_asset ADD CONSTRAINT FK_DOCUMENT_THEME_ASSET_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- changeset auto.generated:1825492372-37
ALTER TABLE document_theme_version ADD CONSTRAINT FK_DOCUMENT_THEME_VERSION_THEME FOREIGN KEY (theme) REFERENCES document_theme (uid) ON UPDATE RESTRICT ON DELETE RESTRICT;

