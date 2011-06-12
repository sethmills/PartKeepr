Ext.define('PartDB2.UnitEditor', {
	extend: 'PartDB2.Editor',
	alias: 'widget.UnitEditor',
	saveText: i18n("Save Unit"),
	model: 'PartDB2.Unit',
	initComponent: function () {
		
		var sm = Ext.create('Ext.selection.CheckboxModel',{
			checkOnly: true
		});
		
		this.gridPanel = Ext.create("Ext.grid.Panel", {
			store: PartDB2.getApplication().getSiPrefixStore(),
			selModel: sm,
			columnLines: true,
			columns: [
			          { text: i18n("Prefix"), dataIndex: "prefix", width: 60 },
			          { text: i18n("Symbol"), dataIndex: "symbol", width: 60 },
			          { text: i18n("Power"), dataIndex: "power", flex: 1, renderer: function (val) { return "10<sup>"+val+"</sup>"; } }
			          ]
		});

		var container = Ext.create("Ext.form.FieldContainer", {
			fieldLabel: i18n("Allowed SI-Prefixes"),
			labelWidth: 150,
			items: this.gridPanel
		});
		
		this.items = [{
				xtype: 'textfield',
				name: 'name',
				fieldLabel: i18n("Unit Name")
			},{
				xtype: 'textfield',
				name: 'symbol',
				fieldLabel: i18n("Symbol")
			},
			container];
		
		this.callParent();
		
		this.on("startEdit", this.onStartEdit, this);
	},
	onStartEdit: function () {
		var records = this.record.prefixes().getRange();
		
		var toSelect = [];
		var pfxStore = PartDB2.getApplication().getSiPrefixStore();
		
		for (var i=0;i<records.length;i++) {
			toSelect.push(pfxStore.getAt(pfxStore.find("id", records[i].get("id"))));
		}
		
		// @todo I don't like defer too much, can we fix that somehow?
		Ext.defer(function () { this.gridPanel.getSelectionModel().select(toSelect); }, 100, this);
	},
	onItemSave: function () {
		
		var selection = this.gridPanel.getSelectionModel().getSelection();
		var records = [];
		for (var i=0;i<selection.length;i++) {
			records.push(selection[i].get("id"));
		}
		
		var call = new PartDB2.ServiceCall("Unit", "setUnitPrefixes");
		call.setParameter("prefixes", records);
		call.setParameter("id", this.record.get("id"));
		call.doCall();
		
		this.callParent();
	}
});
