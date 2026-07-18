# V1.3.8 Bug Register

| ID | Severity | Route / role | Reproduction | Root cause | Resolution | Evidence status |
| --- | --- | --- | --- | --- | --- | --- |
| CORE-138-001 | High | `customers`, admin | Open customer cards with Feather replacement unavailable | action glyphs depended on late icon-font replacement | local `proma_icon()` SVG component | PASSED: local SVG controls visible in browser; long names wrap safely |
| CORE-138-002 | High | `contracts`, admin | Use cards at tablet width | non-wrapping action row set `overflow-x:auto` | container-aware grid and wrapping action layout | PASSED: card actions and direct card navigation verified in browser |
| CORE-138-003 | High | `overdue`, admin | Open an overdue installment as management | contact button existed only in operator branch; copy action was omitted from initial JS boot | common contact trigger, normalized URI and initial `initContactActions()` call | PASSED: manager modal and copy control binding verified in browser |
| CORE-138-004 | High | `calendar`, admin | Open monthly calendar | controller merged installment events for every role | customer-only system installment merge and server filtering | PASSED: admin calendar has no automatic installment event |
| CORE-138-005 | High | `file-manager`, admin | upload/manage a file | directory-only manager had no database, relation or audit lifecycle | `FileRecord`, migration and protected workflow | PASSED: idempotent migration plus browser upload/archive/restore and integration test |
| CORE-138-006 | Medium | all modal forms | use mobile virtual keyboard | fixed 88vh content area could clip form/footer | dynamic viewport modal layout | PASSED: `390x844` file modal has no control overflow and a visible footer |
| CORE-138-007 | Medium | `medals`, admin | inspect definitions/history | raw JSON form, no overview, no sync workflow | redesigned management page and safe batch sync | PASSED: management screen and history rendered without browser console errors |

Release rule: all V1.3.8 rows above have targeted `PASSED` evidence. Full command output is recorded in `docs/qa/V1_3_8_TEST_RESULTS.md`.
