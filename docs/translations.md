# Translations — Translation groups

Contents can be linked together as translations of the same content. The link goes through a `TranslationGroup`.

## Concepts

- A `TranslationGroup` is a container: it groups Contents that are translations of each other.
- Each Content has a `languageId` (FK to `Language`, e.g. `"fr"`, `"en"`).
- A group cannot contain two Contents with the same language.
- The group is created/deleted automatically by the API.

## Linking two translations

```php
$contentFr->linkTranslation($contentEn);
```

Behavior:
- If neither has a group → creates a `TranslationGroup` and assigns both.
- If one already has a group → assigns the other to the same group.
- If both already have a group → `LogicException`.
- If the target language already exists in the group → `LogicException`.
- If both have the same language → `LogicException`.
- Transactional.

Accepts a Content or an ID (int or numeric string):

```php
$contentFr->linkTranslation($contentEn);  // Content
$contentFr->linkTranslation(42);           // ID
```

## Unlinking a translation

```php
$contentFr->unlinkTranslation();           // removes self from the group
$contentFr->unlinkTranslation($contentEn); // removes a specific Content
$contentFr->unlinkTranslation(42);         // by ID
$contentFr->unlinkTranslation('en');       // by language (string)
$contentFr->unlinkTranslation($language);  // by Language object
```

Behavior:
- Removes the Content from the group (`translationGroupId = null`).
- If the group has only one member left → also removes the last member and deletes the group.
- `InvalidArgumentException` if the Content is not in a group or if the target is not found.
- Transactional.

## Reading translations

```php
$content->getTranslationsQuery(): ActiveQueryInterface
```

Returns the other Contents in the same `TranslationGroup` (excludes self). If `translationGroupId` is `null`, returns an empty query.

```php
$translations = $content->getTranslationsQuery()->all();

// Filter by language
$english = $content->getTranslationsQuery()
    ->andWhere(['languageId' => 'en'])
    ->one();
```

## Cascade

When a Content is deleted (`delete()`), the `TranslationGroup` is deleted if it becomes empty.

## Language

| Method | Type | Description |
|---|---|---|
| `getId()` | `?string` | Language code (e.g. `"fr"`, `"en"`) — string PK |
| `getName()` | `string` | Display name (e.g. `"Français"`) |
| `isMain()` | `bool` | Main language vs regional variant |
| `isActive()` | `bool` | |
